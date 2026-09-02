<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use PDO;

final class MySqlSalesOrderRepository implements SalesOrderRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?SalesOrder
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales_orders WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? SalesOrder::fromRow($row) : null;
    }

    public function findWithItems(int $id): ?SalesOrder
    {
        $so = $this->findById($id);
        if ($so === null) {
            return null;
        }
        $so->items = $this->itemsFor($id);

        return $so;
    }

    public function soNumberExists(string $soNumber): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM sales_orders WHERE so_number = ?');
        $stmt->execute([$soNumber]);

        return $stmt->fetch() !== false;
    }

    private function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['customer_id'])) {
            $where[] = 'customer_id = ?';
            $params[] = (int) $filters['customer_id'];
        }
        if (!empty($filters['warehouse_id'])) {
            $where[] = 'warehouse_id = ?';
            $params[] = (int) $filters['warehouse_id'];
        }
        if (!empty($filters['created_by'])) {
            $where[] = 'created_by = ?';
            $params[] = (int) $filters['created_by'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'order_date >= ?';
            $params[] = (string) $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'order_date <= ?';
            $params[] = (string) $filters['date_to'];
        }

        return [$where, $params];
    }

    public function search(array $filters = []): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = 'SELECT * FROM sales_orders';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC';

        $limit = (int) ($filters['limit'] ?? 10);
        $offset = (int) ($filters['offset'] ?? 0);
        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(fn ($r) => SalesOrder::fromRow($r), $stmt->fetchAll());
    }

    public function countSearch(array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = 'SELECT COUNT(*) FROM sales_orders';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function save(SalesOrder $so): SalesOrder
    {
        if ($so->id === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO sales_orders (so_number, customer_id, warehouse_id, created_by, approved_by, status, order_date)
                 VALUES (?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $so->soNumber, $so->customerId, $so->warehouseId, $so->createdBy,
                $so->approvedBy, $so->status, $so->orderDate,
            ]);
            $so->id = (int) $this->pdo->lastInsertId();

            return $so;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE sales_orders SET so_number=?, customer_id=?, warehouse_id=?, approved_by=?, status=?, order_date=? WHERE id=?'
        );
        $stmt->execute([
            $so->soNumber, $so->customerId, $so->warehouseId, $so->approvedBy, $so->status, $so->orderDate, $so->id,
        ]);

        return $so;
    }

    public function saveItem(SalesOrderItem $item): SalesOrderItem
    {
        if ($item->id === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO sales_order_items (sales_order_id, product_id, qty, selling_price) VALUES (?,?,?,?)'
            );
            $stmt->execute([$item->salesOrderId, $item->productId, $item->qty, $item->sellingPrice]);
            $item->id = (int) $this->pdo->lastInsertId();

            return $item;
        }

        $stmt = $this->pdo->prepare('UPDATE sales_order_items SET product_id=?, qty=?, selling_price=? WHERE id=?');
        $stmt->execute([$item->productId, $item->qty, $item->sellingPrice, $item->id]);

        return $item;
    }

    public function itemsFor(int $soId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales_order_items WHERE sales_order_id = ?');
        $stmt->execute([$soId]);

        return array_map(fn ($r) => SalesOrderItem::fromRow($r), $stmt->fetchAll());
    }

    public function updateStatus(int $soId, string $status, ?int $approvedBy = null, ?PDO $pdo = null): void
    {
        $conn = $pdo ?? $this->pdo;
        if ($approvedBy !== null) {
            $stmt = $conn->prepare('UPDATE sales_orders SET status = ?, approved_by = ? WHERE id = ?');
            $stmt->execute([$status, $approvedBy, $soId]);

            return;
        }

        $stmt = $conn->prepare('UPDATE sales_orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, $soId]);
    }

    public function countByStatus(string $status): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM sales_orders WHERE status = ?');
        $stmt->execute([$status]);

        return (int) $stmt->fetchColumn();
    }
}
