<?php

declare(strict_types=1);

namespace App\Entity;

final class SalesOrder
{
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_PENDING_APPROVAL = 'PendingApproval';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_FULFILLED = 'Fulfilled';
    public const STATUS_CANCELLED = 'Cancelled';

    /** @var SalesOrderItem[] */
    public array $items = [];

    public function __construct(
        public ?int $id,
        public string $soNumber,
        public int $customerId,
        public int $warehouseId,
        public int $createdBy,
        public ?int $approvedBy,
        public string $status,
        public string $orderDate,
        public ?string $customerName = null,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['so_number'],
            (int) $row['customer_id'],
            (int) $row['warehouse_id'],
            (int) $row['created_by'],
            isset($row['approved_by']) ? (int) $row['approved_by'] : null,
            (string) $row['status'],
            (string) $row['order_date'],
            isset($row['customer_name']) ? (string) $row['customer_name'] : null,
        );
    }
}
