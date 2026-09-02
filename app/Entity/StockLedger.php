<?php

declare(strict_types=1);

namespace App\Entity;

final class StockLedger
{
    public const TYPE_RECEIPT = 'Receipt';
    public const TYPE_ISSUE = 'Issue';
    public const TYPE_ADJUSTMENT = 'Adjustment';

    public function __construct(
        public ?int $id,
        public int $productId,
        public int $warehouseId,
        public string $movementType,
        public int $quantity,
        public string $referenceType,
        public int $referenceId,
        public int $performedBy,
        public ?string $createdAt = null,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['product_id'],
            (int) $row['warehouse_id'],
            (string) $row['movement_type'],
            (int) $row['quantity'],
            (string) $row['reference_type'],
            (int) $row['reference_id'],
            (int) $row['performed_by'],
            $row['created_at'] ?? null,
        );
    }
}
