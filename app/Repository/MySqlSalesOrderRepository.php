<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use PDO;

final class MySqlSalesOrderRepository extends AbstractMySqlRepository implements SalesOrderRepositoryInterface
{
    private const RULES = [
        [self::COL_STATUS, 'status = ?', 'string'],
        ['customer_id', 'customer_id = ?', 'int'],
        [self::COL_WAREHOUSE_ID, 'warehouse_id = ?', 'int'],
        ['created_by', 'created_by = ?', 'int'],
        [self::COL_DATE_FROM, 'order_date >= ?', 'string'],
        [self::COL_DATE_TO, 'order_date <= ?', 'string'],
    ];

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

    public function soNumberExists(string $soNumber): bool
    {
        return $this->valueExists('sales_orders', 'so_number', $soNumber);
    }

    public function search(array $filters = []): array
    {
        $rows = $this->searchRows(
            'SELECT id, so_number, customer_id, warehouse_id, created_by, approved_by, status, order_date
             FROM sales_orders',
            $filters,
            self::RULES,
            $this->dateOrder($filters),
            10
        );

        return array_map(fn ($r) => SalesOrder::fromRow($r), $rows);
    }

    public function countSearch(array $filters = []): int
    {
        return $this->countRows('sales_orders', $filters, self::RULES);
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

    public function updateStatus(int $soId, string $status, ?int $approvedBy = null, ?PDO $pdo = null): void
    {
        if ($approvedBy !== null) {
            $this->execute(
                'UPDATE sales_orders SET status = ?, approved_by = ? WHERE id = ?',
                [$status, $approvedBy, $soId],
                $pdo
            );

            return;
        }

        $this->execute('UPDATE sales_orders SET status = ? WHERE id = ?', [$status, $soId], $pdo);
    }

    public function countsByStatus(): array
    {
        return $this->countsByStatusFor('sales_orders');
    }
}
