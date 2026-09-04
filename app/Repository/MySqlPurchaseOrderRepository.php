<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use PDO;

final class MySqlPurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?PurchaseOrder
    {
        $stmt = $this->pdo->prepare('SELECT * FROM purchase_orders WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? PurchaseOrder::fromRow($row) : null;
    }

    public function findWithItems(int $id): ?PurchaseOrder
    {
        $po = $this->findById($id);
        if ($po === null) {
            return null;
        }
        $po->items = $this->itemsFor($id);

        return $po;
    }

    public function poNumberExists(string $poNumber): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM purchase_orders WHERE po_number = ?');
        $stmt->execute([$poNumber]);

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
        if (!empty($filters['supplier_id'])) {
            $where[] = 'supplier_id = ?';
            $params[] = (int) $filters['supplier_id'];
        }
        if (!empty($filters['warehouse_id'])) {
            $where[] = 'warehouse_id = ?';
            $params[] = (int) $filters['warehouse_id'];
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
        $sql = 'SELECT po.*, s.name AS supplier_name
                FROM purchase_orders po
                LEFT JOIN suppliers s ON s.id = po.supplier_id';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $direction = ($filters['sort'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY po.order_date {$direction}, po.created_at {$direction}";

        $limit = (int) ($filters['limit'] ?? 10);
        $offset = (int) ($filters['offset'] ?? 0);
        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(fn ($r) => PurchaseOrder::fromRow($r), $stmt->fetchAll());
    }

    public function countSearch(array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = 'SELECT COUNT(*) FROM purchase_orders';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function save(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->id === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO purchase_orders (po_number, supplier_id, warehouse_id, status, order_date, created_by)
                 VALUES (?,?,?,?,?,?)'
            );
            $stmt->execute([$po->poNumber, $po->supplierId, $po->warehouseId, $po->status, $po->orderDate, $po->createdBy]);
            $po->id = (int) $this->pdo->lastInsertId();

            return $po;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE purchase_orders SET po_number=?, supplier_id=?, warehouse_id=?, status=?, order_date=? WHERE id=?'
        );
        $stmt->execute([$po->poNumber, $po->supplierId, $po->warehouseId, $po->status, $po->orderDate, $po->id]);

        return $po;
    }

    public function saveItem(PurchaseOrderItem $item): PurchaseOrderItem
    {
        if ($item->id === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO purchase_order_items (purchase_order_id, product_id, qty_ordered, qty_received, purchase_price)
                 VALUES (?,?,?,?,?)'
            );
            $stmt->execute([$item->purchaseOrderId, $item->productId, $item->qtyOrdered, $item->qtyReceived, $item->purchasePrice]);
            $item->id = (int) $this->pdo->lastInsertId();

            return $item;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE purchase_order_items SET product_id=?, qty_ordered=?, qty_received=?, purchase_price=? WHERE id=?'
        );
        $stmt->execute([$item->productId, $item->qtyOrdered, $item->qtyReceived, $item->purchasePrice, $item->id]);

        return $item;
    }

    public function itemsFor(int $poId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM purchase_order_items WHERE purchase_order_id = ?');
        $stmt->execute([$poId]);

        return array_map(fn ($r) => PurchaseOrderItem::fromRow($r), $stmt->fetchAll());
    }

    public function updateItemReceived(int $itemId, int $qtyReceived, ?PDO $pdo = null): void
    {
        $conn = $pdo ?? $this->pdo;
        $stmt = $conn->prepare('UPDATE purchase_order_items SET qty_received = ? WHERE id = ?');
        $stmt->execute([$qtyReceived, $itemId]);
    }

    public function updateStatus(int $poId, string $status, ?PDO $pdo = null): void
    {
        $conn = $pdo ?? $this->pdo;
        $stmt = $conn->prepare('UPDATE purchase_orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, $poId]);
    }
}
