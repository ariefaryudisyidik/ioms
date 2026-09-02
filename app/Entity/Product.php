<?php

declare(strict_types=1);

namespace App\Entity;

final class Product
{
    public function __construct(
        public ?int $id,
        public string $sku,
        public string $name,
        public int $categoryId,
        public string $unit,
        public float $purchasePrice,
        public float $sellingPrice,
        public int $reorderPoint,
        public ?string $imagePath = null,
        public bool $isActive = true,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['sku'],
            (string) $row['name'],
            (int) $row['category_id'],
            (string) $row['unit'],
            (float) $row['purchase_price'],
            (float) $row['selling_price'],
            (int) $row['reorder_point'],
            $row['image_path'] ?? null,
            (bool) $row['is_active'],
        );
    }
}
