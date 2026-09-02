<?php

declare(strict_types=1);

namespace App\Entity;

final class ProductStock
{
    public function __construct(
        public ?int $id,
        public int $productId,
        public int $warehouseId,
        public int $quantity,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) $row['product_id'],
            (int) $row['warehouse_id'],
            (int) $row['quantity'],
        );
    }
}
