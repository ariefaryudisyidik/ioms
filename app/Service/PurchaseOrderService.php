<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\StockLedger;
use App\Repository\ProductRepositoryInterface;
use App\Repository\ProductStockRepositoryInterface;
use App\Repository\PurchaseOrderRepositoryInterface;
use App\Repository\StockLedgerRepositoryInterface;
use App\Repository\TransactionManagerInterface;
use App\Service\Exception\InvalidStatusTransitionException;
use App\Service\Exception\ValidationException;
use DateTimeImmutable;

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
        private TransactionManagerInterface $transactions,
        private ProductRepositoryInterface $products,
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
        if (!DateRules::isValidYmd($orderDate)) {
            return 'Order date must be a valid date (YYYY-MM-DD).';
        }

        $date = new DateTimeImmutable($orderDate);
        $limit = (new DateTimeImmutable('today'))->modify("+{$this->futureDateToleranceDays} days");

        return $date > $limit ? 'Order date cannot be too far in the future.' : null;
    }

    /**
     * The PO number is generated (PO-<order year>-<id>) and each item takes its
     * purchase price from the product at the time of ordering.
     *
     * @param array<string,mixed> $data raw input from request, keys not guaranteed present
     */
    public function create(array $data, int $userId): PurchaseOrder
    {
        $errors = [];

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
        $errors += OrderItemValidator::validate($items, 'qty_ordered');

        if (!$errors) {
            $errors = $this->purchaseOrders->invalidReferences(
                (int) $data['supplier_id'],
                (int) $data['warehouse_id'],
                OrderItemValidator::productIds($items)
            );
        }

        if ($errors) {
            throw new ValidationException($errors);
        }

        return $this->transactions->run(fn (): PurchaseOrder => $this->persist($data, $items, $userId));
    }

    /**
     * @param array<string,mixed> $data validated input
     * @param array<int|string,array<string,mixed>> $items validated items
     */
    private function persist(array $data, array $items, int $userId): PurchaseOrder
    {
        // Saved with a unique placeholder first so the final number can be derived from the new id.
        $po = $this->purchaseOrders->save(new PurchaseOrder(
            id: null,
            poNumber: 'PENDING-' . bin2hex(random_bytes(8)),
            supplierId: (int) $data['supplier_id'],
            warehouseId: (int) $data['warehouse_id'],
            status: PurchaseOrder::STATUS_DRAFT,
            orderDate: $data['order_date'],
            createdBy: $userId,
        ));
        $po->poNumber = sprintf('PO-%s-%04d', substr($po->orderDate, 0, 4), $po->id);
        $this->purchaseOrders->save($po);

        foreach ($items as $item) {
            $product = $this->products->findById((int) $item['product_id']);
            $this->purchaseOrders->saveItem(new PurchaseOrderItem(
                id: null,
                purchaseOrderId: $po->id,
                productId: (int) $item['product_id'],
                qtyOrdered: (int) $item['qty_ordered'],
                qtyReceived: 0,
                purchasePrice: $product?->purchasePrice ?? 0.0,
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

        $this->assertValidQuantities($itemsById, $items);

        return $this->transactions->run(function () use ($po, $itemsById, $items, $userId): PurchaseOrder {
            foreach ($items as $received) {
                $this->receiveLine($po, $itemsById, (int) $received['item_id'], (int) $received['qty'], $userId);
            }

            $allFullyReceived = array_reduce(
                $itemsById,
                static fn (bool $all, PurchaseOrderItem $line): bool => $all && $line->qtyReceived >= $line->qtyOrdered,
                true
            );
            $newStatus = $allFullyReceived ? PurchaseOrder::STATUS_RECEIVED : PurchaseOrder::STATUS_PARTIALLY_RECEIVED;
            $this->purchaseOrders->updateStatus((int) $po->id, $newStatus);
            $po->status = $newStatus;

            return $po;
        });
    }

    /**
     * Rejects the whole receipt (nothing is saved) when no line receives anything
     * or when a line would receive more than is still outstanding.
     *
     * @param array<int,PurchaseOrderItem> $itemsById
     * @param array<int,array{item_id:int,qty:int}> $items
     */
    private function assertValidQuantities(array $itemsById, array $items): void
    {
        $errors = [];
        $receivesSomething = false;
        foreach ($items as $received) {
            $line = $itemsById[(int) $received['item_id']] ?? null;
            if ($line === null) {
                continue;
            }
            $receivesSomething = $receivesSomething || (int) $received['qty'] > 0;
            if ((int) $received['qty'] <= $line->remaining()) {
                continue;
            }

            $name = $this->products->findById($line->productId)?->name ?? ('#' . $line->productId);
            $errors['items.' . $line->id] = sprintf(
                '%s: cannot receive %d, only %d remaining.',
                $name,
                (int) $received['qty'],
                $line->remaining()
            );
        }

        if (!$errors && !$receivesSomething) {
            $errors['items'] = 'Enter a quantity to receive for at least one item.';
        }

        if ($errors) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Applies one received quantity (already validated against the remaining
     * quantity): updates the item, writes the Receipt ledger row, and adds stock.
     *
     * @param array<int,PurchaseOrderItem> $itemsById
     */
    private function receiveLine(PurchaseOrder $po, array $itemsById, int $itemId, int $qty, int $userId): void
    {
        if ($qty <= 0 || !isset($itemsById[$itemId])) {
            return;
        }

        $poItem = $itemsById[$itemId];
        $newReceived = $poItem->qtyReceived + $qty;

        $this->purchaseOrders->updateItemReceived($itemId, $newReceived);
        $this->ledger->record(new StockLedger(
            id: null,
            productId: $poItem->productId,
            warehouseId: $po->warehouseId,
            movementType: StockLedger::TYPE_RECEIPT,
            quantity: $qty,
            referenceType: 'purchase_order',
            referenceId: $po->id,
            performedBy: $userId,
        ));
        $this->stocks->increment($poItem->productId, $po->warehouseId, $qty);

        $poItem->qtyReceived = $newReceived;
    }
}
