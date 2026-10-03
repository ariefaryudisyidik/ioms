<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Entity\StockLedger;
use App\Repository\ProductStockRepositoryInterface;
use App\Repository\SalesOrderRepositoryInterface;
use App\Repository\StockLedgerRepositoryInterface;
use App\Service\Exception\AuthorizationException;
use App\Service\Exception\InsufficientStockException;
use App\Service\Exception\InvalidStatusTransitionException;
use App\Service\Exception\ValidationException;
use PDO;
use Throwable;

final class SalesOrderService
{
    private const ORDER_NOT_FOUND = 'Sales order not found.';

    private const TRANSITIONS = [
        SalesOrder::STATUS_DRAFT => [SalesOrder::STATUS_PENDING_APPROVAL, SalesOrder::STATUS_CANCELLED],
        SalesOrder::STATUS_PENDING_APPROVAL => [SalesOrder::STATUS_APPROVED, SalesOrder::STATUS_DRAFT, SalesOrder::STATUS_CANCELLED],
        SalesOrder::STATUS_APPROVED => [SalesOrder::STATUS_FULFILLED, SalesOrder::STATUS_CANCELLED],
        SalesOrder::STATUS_FULFILLED => [],
        SalesOrder::STATUS_CANCELLED => [],
    ];

    public function __construct(
        private SalesOrderRepositoryInterface $salesOrders,
        private ProductStockRepositoryInterface $stocks,
        private StockLedgerRepositoryInterface $ledger,
        private PDO $pdo,
    ) {
    }

    /**
     * @param array<string,mixed> $data raw input from request, keys not guaranteed present
     */
    public function create(array $data, int $userId): SalesOrder
    {
        $errors = [];

        $soNumber = trim((string) ($data['so_number'] ?? ''));
        if ($soNumber === '') {
            $errors['so_number'] = 'SO number is required.';
        } elseif ($this->salesOrders->soNumberExists($soNumber)) {
            $errors['so_number'] = 'This SO number already exists.';
        }

        if (empty($data['customer_id'])) {
            $errors['customer_id'] = 'Customer is required.';
        }
        if (empty($data['warehouse_id'])) {
            $errors['warehouse_id'] = 'Warehouse is required.';
        }
        $orderDate = trim((string) ($data['order_date'] ?? ''));
        if ($orderDate === '') {
            $errors['order_date'] = 'Order date is required.';
        } elseif (!DateRules::isValidYmd($orderDate)) {
            $errors['order_date'] = 'Order date must be a valid date (YYYY-MM-DD).';
        }

        $items = $data['items'] ?? [];
        $errors += OrderItemValidator::validate($items, 'qty', 'selling_price');

        if (!$errors) {
            $errors = $this->salesOrders->invalidReferences(
                (int) $data['customer_id'],
                (int) $data['warehouse_id'],
                OrderItemValidator::productIds($items)
            );
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        $so = new SalesOrder(
            id: null,
            soNumber: $soNumber,
            customerId: (int) $data['customer_id'],
            warehouseId: (int) $data['warehouse_id'],
            createdBy: $userId,
            approvedBy: null,
            status: SalesOrder::STATUS_DRAFT,
            orderDate: $orderDate,
        );
        $so = $this->salesOrders->save($so);

        foreach ($items as $item) {
            $this->salesOrders->saveItem(new SalesOrderItem(
                id: null,
                salesOrderId: $so->id,
                productId: (int) $item['product_id'],
                qty: (int) $item['qty'],
                sellingPrice: (float) ($item['selling_price'] ?? 0),
            ));
        }

        return $so;
    }

    /**
     * Draft -> PendingApproval. Only the creator/owner may submit.
     */
    public function submitForApproval(int $soId, int $userId): SalesOrder
    {
        $so = $this->salesOrders->findById($soId);
        if ($so === null) {
            throw new ValidationException(['id' => self::ORDER_NOT_FOUND]);
        }

        if ($so->createdBy !== $userId) {
            throw AuthorizationException::forbidden('Only the order owner can submit it for approval.');
        }

        $this->assertTransition($so->status, SalesOrder::STATUS_PENDING_APPROVAL);
        $this->salesOrders->updateStatus($soId, SalesOrder::STATUS_PENDING_APPROVAL);
        $so->status = SalesOrder::STATUS_PENDING_APPROVAL;

        return $so;
    }

    /**
     * PendingApproval -> Approved. Admin role only, and the approver may
     * not be the same person who created the order.
     */
    public function approve(int $soId, int $approverId, string $approverRole): SalesOrder
    {
        if ($approverRole !== 'Admin') {
            throw AuthorizationException::forbidden('Only an Admin can approve sales orders.');
        }

        $so = $this->salesOrders->findById($soId);
        if ($so === null) {
            throw new ValidationException(['id' => self::ORDER_NOT_FOUND]);
        }

        if ($so->createdBy === $approverId) {
            throw AuthorizationException::forbidden('An order cannot be approved by its own creator.');
        }

        $this->assertTransition($so->status, SalesOrder::STATUS_APPROVED);
        $this->salesOrders->updateStatus($soId, SalesOrder::STATUS_APPROVED, $approverId);
        $so->status = SalesOrder::STATUS_APPROVED;
        $so->approvedBy = $approverId;

        return $so;
    }

    /**
     * PendingApproval -> Draft (rejected back to the drafting stage).
     * Admin role only.
     */
    public function reject(int $soId, string $approverRole): SalesOrder
    {
        if ($approverRole !== 'Admin') {
            throw AuthorizationException::forbidden('Only an Admin can reject sales orders.');
        }

        $so = $this->salesOrders->findById($soId);
        if ($so === null) {
            throw new ValidationException(['id' => self::ORDER_NOT_FOUND]);
        }

        $this->assertTransition($so->status, SalesOrder::STATUS_DRAFT);
        $this->salesOrders->updateStatus($soId, SalesOrder::STATUS_DRAFT);
        $so->status = SalesOrder::STATUS_DRAFT;

        return $so;
    }

    public function cancel(int $soId, int $userId, string $role): SalesOrder
    {
        $so = $this->salesOrders->findById($soId);
        if ($so === null) {
            throw new ValidationException(['id' => self::ORDER_NOT_FOUND]);
        }

        if ($role === 'Sales' && $so->createdBy !== $userId) {
            throw AuthorizationException::forbidden('Only the order owner can cancel it.');
        }

        $this->assertTransition($so->status, SalesOrder::STATUS_CANCELLED);
        $this->salesOrders->updateStatus($soId, SalesOrder::STATUS_CANCELLED);
        $so->status = SalesOrder::STATUS_CANCELLED;

        return $so;
    }

    /**
     * Goods issue: Approved -> Fulfilled. Runs in a single PDO transaction
     * that locks each affected product_stocks row with SELECT ... FOR
     * UPDATE, verifies sufficient quantity, and only then decrements stock
     * and writes the ledger. Any shortage rolls the whole transaction back
     * and throws InsufficientStockException.
     */
    public function fulfill(int $soId, int $userId): SalesOrder
    {
        $so = $this->salesOrders->findWithItems($soId);
        if ($so === null) {
            throw new ValidationException(['id' => self::ORDER_NOT_FOUND]);
        }

        $this->assertTransition($so->status, SalesOrder::STATUS_FULFILLED);

        $this->pdo->beginTransaction();

        try {
            foreach ($so->items as $item) {
                $available = $this->stocks->lockForUpdate($this->pdo, $item->productId, $so->warehouseId);

                if ($available < $item->qty) {
                    throw InsufficientStockException::forProduct($item->productId, $item->qty, $available);
                }
            }

            foreach ($so->items as $item) {
                $this->stocks->decrement($item->productId, $so->warehouseId, $item->qty);

                $this->ledger->record(new StockLedger(
                    id: null,
                    productId: $item->productId,
                    warehouseId: $so->warehouseId,
                    movementType: StockLedger::TYPE_ISSUE,
                    quantity: $item->qty,
                    referenceType: 'sales_order',
                    referenceId: $so->id,
                    performedBy: $userId,
                ), $this->pdo);
            }

            $this->salesOrders->updateStatus($soId, SalesOrder::STATUS_FULFILLED, null, $this->pdo);
            $so->status = SalesOrder::STATUS_FULFILLED;

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $so;
    }

    private function assertTransition(string $from, string $to): void
    {
        if ($to === SalesOrder::STATUS_CANCELLED) {
            if (in_array($from, [SalesOrder::STATUS_FULFILLED, SalesOrder::STATUS_CANCELLED], true)) {
                throw InvalidStatusTransitionException::make($from, $to);
            }

            return;
        }

        if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw InvalidStatusTransitionException::make($from, $to);
        }
    }
}
