<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\StockLedger;
use App\Repository\ProductStockRepositoryInterface;
use App\Repository\PurchaseOrderRepositoryInterface;
use App\Repository\StockLedgerRepositoryInterface;
use App\Service\Exception\InvalidStatusTransitionException;
use App\Service\Exception\ValidationException;
use DateTimeImmutable;
use PDO;
use Throwable;

final class PurchaseOrderService
{
    /** Allowed forward transitions, Cancelled reachable from any non-terminal state. */
    private const TRANSITIONS = [
        PurchaseOrder::STATUS_DRAFT => [PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_CANCELLED],
        PurchaseOrder::STATUS_ORDERED => [PurchaseOrder::STATUS_PARTIALLY_RECEIVED, PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CANCELLED],
        PurchaseOrder::STATUS_PARTIALLY_RECEIVED => [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CANCELLED],
        PurchaseOrder::STATUS_RECEIVED => [],
        PurchaseOrder::STATUS_CANCELLED => [],
    ];

    public function __construct(
        private PurchaseOrderRepositoryInterface $purchaseOrders,
        private ProductStockRepositoryInterface $stocks,
        private StockLedgerRepositoryInterface $ledger,
        private PDO $pdo,
        /** Tolerance in days for how far in the future an order_date may be. */
        private int $futureDateToleranceDays = 0,
    ) {
    }

    /**
     * Validate order_date: must not be empty, must be a valid Y-m-d date,
     * and must not be further in the future than the configured tolerance.
     */
    public function validateOrderDate(?string $orderDate): ?string
    {
        if ($orderDate === null || trim($orderDate) === '') {
            $error = 'Order date is required.';
        } else {
            $error = $this->checkDateValue($orderDate);
        }

        return $error;
    }

    private function checkDateValue(string $orderDate): ?string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $orderDate);
        $errors = DateTimeImmutable::getLastErrors();
        $hasParseIssues = $errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
        $isValid = $date !== false && !$hasParseIssues;
        if (!$isValid) {
            return 'Order date must be a valid date (YYYY-MM-DD).';
        }

        $limit = (new DateTimeImmutable('today'))->modify("+{$this->futureDateToleranceDays} days");

        return $date > $limit ? 'Order date cannot be too far in the future.' : null;
    }

    /**
     * @return array<string,string>
     */
    private function validateItems(mixed $items, string $qtyKey): array
    {
        if (!is_array($items) || count($items) === 0) {
            return ['items' => 'At least one item is required.'];
        }

        $errors = [];
        foreach ($items as $i => $item) {
            if (empty($item['product_id']) || (int) ($item[$qtyKey] ?? 0) <= 0) {
                $errors["items.$i"] = 'Each item requires a product and a positive quantity.';
            }
        }

        return $errors;
    }

    /**
     * @param array<string,mixed> $data raw input from request, keys not guaranteed present
     */
    public function create(array $data, int $userId): PurchaseOrder
    {
        $errors = [];

        $poNumber = trim((string) ($data['po_number'] ?? ''));
        if ($poNumber === '') {
            $errors['po_number'] = 'PO number is required.';
        } elseif ($this->purchaseOrders->poNumberExists($poNumber)) {
            $errors['po_number'] = 'This PO number already exists.';
        }

        if (empty($data['supplier_id'])) {
            $errors['supplier_id'] = 'Supplier is required.';
        }
        if (empty($data['warehouse_id'])) {
            $errors['warehouse_id'] = 'Warehouse is required.';
        }

        $dateError = $this->validateOrderDate($data['order_date'] ?? null);
        if ($dateError !== null) {
            $errors['order_date'] = $dateError;
        }

        $items = $data['items'] ?? [];
        $errors += $this->validateItems($items, 'qty_ordered');

        if ($errors) {
            throw new ValidationException($errors);
        }

        $po = new PurchaseOrder(
            id: null,
            poNumber: $poNumber,
            supplierId: (int) $data['supplier_id'],
            warehouseId: (int) $data['warehouse_id'],
            status: PurchaseOrder::STATUS_DRAFT,
            orderDate: $data['order_date'],
            createdBy: $userId,
        );
        $po = $this->purchaseOrders->save($po);

        foreach ($items as $item) {
            $this->purchaseOrders->saveItem(new PurchaseOrderItem(
                id: null,
                purchaseOrderId: $po->id,
                productId: (int) $item['product_id'],
                qtyOrdered: (int) $item['qty_ordered'],
                qtyReceived: 0,
                purchasePrice: (float) ($item['purchase_price'] ?? 0),
            ));
        }

        return $po;
    }

    public function transitionTo(int $poId, string $newStatus): PurchaseOrder
    {
        $po = $this->purchaseOrders->findById($poId);
        if ($po === null) {
            throw new ValidationException(['id' => 'Purchase order not found.']);
        }

        $this->assertTransitionAllowed($po->status, $newStatus);
        $this->purchaseOrders->updateStatus($poId, $newStatus);
        $po->status = $newStatus;

        return $po;
    }

    private function assertTransitionAllowed(string $from, string $to): void
    {
        if ($to === PurchaseOrder::STATUS_CANCELLED) {
            if (in_array($from, [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CANCELLED], true)) {
                throw InvalidStatusTransitionException::make($from, $to);
            }

            return;
        }

        if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw InvalidStatusTransitionException::make($from, $to);
        }
    }

    /**
     * Goods receipt: applies received quantities to a PO in a single
     * transaction — updates qty_received per item, writes stock_ledger
     * Receipt entries, upserts product_stocks, and recalculates the PO
     * status (PartiallyReceived vs Received).
     *
     * @param array<int,array{item_id:int,qty:int}> $items
     */
    public function receiveGoods(int $poId, array $items, int $userId): PurchaseOrder
    {
        $po = $this->purchaseOrders->findWithItems($poId);
        if ($po === null) {
            throw new ValidationException(['id' => 'Purchase order not found.']);
        }

        if (!in_array($po->status, [PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED], true)) {
            throw InvalidStatusTransitionException::make($po->status, PurchaseOrder::STATUS_PARTIALLY_RECEIVED);
        }

        /** @var array<int,PurchaseOrderItem> $itemsById */
        $itemsById = [];
        foreach ($po->items as $poItem) {
            $itemsById[$poItem->id] = $poItem;
        }

        $this->pdo->beginTransaction();

        try {
            $allFullyReceived = true;

            foreach ($items as $received) {
                $itemId = (int) $received['item_id'];
                $qty = (int) $received['qty'];

                if ($qty <= 0 || !isset($itemsById[$itemId])) {
                    continue;
                }

                $poItem = $itemsById[$itemId];
                $newReceived = min($poItem->qtyOrdered, $poItem->qtyReceived + $qty);
                $actualAdded = $newReceived - $poItem->qtyReceived;

                if ($actualAdded <= 0) {
                    continue;
                }

                $this->purchaseOrders->updateItemReceived($itemId, $newReceived, $this->pdo);

                $this->ledger->record(new StockLedger(
                    id: null,
                    productId: $poItem->productId,
                    warehouseId: $po->warehouseId,
                    movementType: StockLedger::TYPE_RECEIPT,
                    quantity: $actualAdded,
                    referenceType: 'purchase_order',
                    referenceId: $po->id,
                    performedBy: $userId,
                ), $this->pdo);

                $this->stocks->increment($poItem->productId, $po->warehouseId, $actualAdded);

                $poItem->qtyReceived = $newReceived;
            }

            foreach ($itemsById as $poItem) {
                if ($poItem->qtyReceived < $poItem->qtyOrdered) {
                    $allFullyReceived = false;
                }
            }

            $newStatus = $allFullyReceived ? PurchaseOrder::STATUS_RECEIVED : PurchaseOrder::STATUS_PARTIALLY_RECEIVED;
            $this->purchaseOrders->updateStatus($poId, $newStatus, $this->pdo);
            $po->status = $newStatus;

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $po;
    }
}
