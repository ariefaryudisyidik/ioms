<?php

declare(strict_types=1);

namespace App\Entity;

final class PurchaseOrder
{
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_ORDERED = 'Ordered';
    public const STATUS_PARTIALLY_RECEIVED = 'PartiallyReceived';
    public const STATUS_RECEIVED = 'Received';
    public const STATUS_CANCELLED = 'Cancelled';

    /** @var PurchaseOrderItem[] */
    public array $items = [];

    public function __construct(
        public ?int $id,
        public string $poNumber,
        public int $supplierId,
        public int $warehouseId,
        public string $status,
        public string $orderDate,
        public int $createdBy,
        public ?string $supplierName = null,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['po_number'],
            (int) $row['supplier_id'],
            (int) $row['warehouse_id'],
            (string) $row['status'],
            (string) $row['order_date'],
            (int) $row['created_by'],
            isset($row['supplier_name']) ? (string) $row['supplier_name'] : null,
        );
    }
}
