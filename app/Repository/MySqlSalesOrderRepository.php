<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;

final class MySqlSalesOrderRepository extends AbstractMySqlRepository implements SalesOrderRepositoryInterface
{
    private const RULES = [
        [self::COL_STATUS, 'status = ?', 'string'],
        ['customer_id', 'customer_id = ?', 'int'],
        [self::COL_WAREHOUSE_ID, 'warehouse_id = ?', 'int'],
        ['created_by', 'created_by = ?', 'int'],
        [self::COL_DATE_FROM, 'order_date >= ?', 'string'],
        [self::COL_DATE_TO, 'order_date <= ?', 'string'],
        ['search', '(so.so_number LIKE ? OR c.name LIKE ?)', 'like'],
    ];
    private const SEARCH_FROM = 'sales_orders so LEFT JOIN customers c ON c.id = so.customer_id';

    public function findById(int $id): ?SalesOrder
    {
        $row = $this->fetchRow(
            'SELECT id, so_number, customer_id, warehouse_id, created_by, approved_by, status, order_date
             FROM sales_orders WHERE id = ?',
            [$id]
        );

        return $row ? SalesOrder::fromRow($row) : null;
    }

    public function findWithItems(int $id): ?SalesOrder
    {
        $so = $this->findById($id);
        if ($so !== null) {
            $so->items = $this->itemsFor($id);
        }

        return $so;
    }

    public function invalidReferences(int $customerId, int $warehouseId, array $productIds): array
    {
        $checks = [
            'customer_id' => ['customers', $customerId, 'Customer not found or inactive.'],
            'warehouse_id' => ['warehouses', $warehouseId, 'Warehouse not found or inactive.'],
        ];
        foreach ($productIds as $index => $productId) {
            $checks["items.$index"] = ['products', $productId, 'Product not found or inactive.'];
        }

        return $this->failedReferenceChecks($checks);
    }

    public function search(array $filters = []): array
    {
        $rows = $this->searchRows(
            'SELECT so.id, so.so_number, so.customer_id, so.warehouse_id, so.created_by, so.approved_by, so.status,
                    so.order_date, c.name AS customer_name
             FROM ' . self::SEARCH_FROM,
            $filters,
            self::RULES,
            $this->dateOrder($filters, 'so.'),
            10
        );

        return array_map(fn ($r) => SalesOrder::fromRow($r), $rows);
    }

    public function countSearch(array $filters = []): int
    {
        return $this->countRows(self::SEARCH_FROM, $filters, self::RULES);
    }

    public function save(SalesOrder $so): SalesOrder
    {
        if ($so->id === null) {
            $so->id = $this->insert(
                'INSERT INTO sales_orders (so_number, customer_id, warehouse_id, created_by, approved_by, status,
                    order_date)
                 VALUES (?,?,?,?,?,?,?)',
                [
                    $so->soNumber, $so->customerId, $so->warehouseId, $so->createdBy,
                    $so->approvedBy, $so->status, $so->orderDate,
                ]
            );

            return $so;
        }

        $this->execute(
            'UPDATE sales_orders SET so_number=?, customer_id=?, warehouse_id=?, approved_by=?, status=?,
                order_date=? WHERE id=?',
            [$so->soNumber, $so->customerId, $so->warehouseId, $so->approvedBy, $so->status, $so->orderDate, $so->id]
        );

        return $so;
    }

    public function saveItem(SalesOrderItem $item): SalesOrderItem
    {
        if ($item->id === null) {
            $item->id = $this->insert(
                'INSERT INTO sales_order_items (sales_order_id, product_id, qty, selling_price) VALUES (?,?,?,?)',
                [$item->salesOrderId, $item->productId, $item->qty, $item->sellingPrice]
            );

            return $item;
        }

        $this->execute(
            'UPDATE sales_order_items SET product_id=?, qty=?, selling_price=? WHERE id=?',
            [$item->productId, $item->qty, $item->sellingPrice, $item->id]
        );

        return $item;
    }

    public function itemsFor(int $soId): array
    {
        $rows = $this->fetchRows(
            'SELECT id, sales_order_id, product_id, qty, selling_price FROM sales_order_items WHERE sales_order_id = ?',
            [$soId]
        );

        return array_map(fn ($r) => SalesOrderItem::fromRow($r), $rows);
    }

    public function updateStatus(int $soId, string $status, ?int $approvedBy = null): void
    {
        if ($approvedBy !== null) {
            $this->execute(
                'UPDATE sales_orders SET status = ?, approved_by = ? WHERE id = ?',
                [$status, $approvedBy, $soId]
            );

            return;
        }

        $this->execute('UPDATE sales_orders SET status = ? WHERE id = ?', [$status, $soId]);
    }

    public function countsByStatus(?int $createdBy = null): array
    {
        return $this->countsByStatusFor('sales_orders', $createdBy);
    }
}
