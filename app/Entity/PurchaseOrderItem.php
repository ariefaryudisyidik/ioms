<?php

declare(strict_types=1);

namespace App\Entity;

final class PurchaseOrderItem
{
    public function __construct(
        public ?int $id,
        public int $purchaseOrderId,
        public int $productId,
        public int $qtyOrdered,
        public int $qtyReceived,
        public float $purchasePrice,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) $row['purchase_order_id'],
            (int) $row['product_id'],
            (int) $row['qty_ordered'],
            (int) $row['qty_received'],
            (float) $row['purchase_price'],
        );
    }

    public function remaining(): int
    {
        return max(0, $this->qtyOrdered - $this->qtyReceived);
    }
}
