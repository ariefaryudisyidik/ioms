<?php

declare(strict_types=1);

namespace App\Entity;

final class SalesOrderItem
{
    public function __construct(
        public ?int $id,
        public int $salesOrderId,
        public int $productId,
        public int $qty,
        public float $sellingPrice,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) $row['sales_order_id'],
            (int) $row['product_id'],
            (int) $row['qty'],
            (float) $row['selling_price'],
        );
    }
}
