<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductStock;
use PDO;

final class MySqlProductStockRepository implements ProductStockRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function find(int $productId, int $warehouseId): ?ProductStock
    {
        $stmt = $this->pdo->prepare('SELECT * FROM product_stocks WHERE product_id = ? AND warehouse_id = ?');
        $stmt->execute([$productId, $warehouseId]);
        $row = $stmt->fetch();

        return $row ? ProductStock::fromRow($row) : null;
    }

    public function findByProduct(int $productId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ps.*, w.name AS warehouse_name FROM product_stocks ps
             JOIN warehouses w ON w.id = ps.warehouse_id
             WHERE ps.product_id = ? ORDER BY w.name ASC'
        );
        $stmt->execute([$productId]);

        return array_map(fn ($r) => ProductStock::fromRow($r), $stmt->fetchAll());
    }

    public function findByWarehouse(int $warehouseId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM product_stocks WHERE warehouse_id = ?');
        $stmt->execute([$warehouseId]);

        return array_map(fn ($r) => ProductStock::fromRow($r), $stmt->fetchAll());
    }

    public function totalForProduct(int $productId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(quantity),0) FROM product_stocks WHERE product_id = ?');
        $stmt->execute([$productId]);

        return (int) $stmt->fetchColumn();
    }

    public function lockForUpdate(PDO $pdo, int $productId, int $warehouseId): int
    {
        $this->ensureRowExists($pdo, $productId, $warehouseId);

        $stmt = $pdo->prepare(
            'SELECT quantity FROM product_stocks WHERE product_id = ? AND warehouse_id = ? FOR UPDATE'
        );
        $stmt->execute([$productId, $warehouseId]);
        $qty = $stmt->fetchColumn();

        return $qty === false ? 0 : (int) $qty;
    }

    public function increment(int $productId, int $warehouseId, int $qty): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO product_stocks (product_id, warehouse_id, quantity)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
        );
        $stmt->execute([$productId, $warehouseId, $qty]);
    }

    /**
     * Ensures a product_stocks row exists for the given product/warehouse
     * pair (inserted at quantity 0 if missing) so it can be locked or
     * updated deterministically. Extracted from lockForUpdate() so any
     * future caller needing the same guarantee does not duplicate the
     * upsert-with-no-op pattern (see docs/quality/refactor-log.md #3).
     */
    private function ensureRowExists(PDO $pdo, int $productId, int $warehouseId): void
    {
        $insert = $pdo->prepare(
            'INSERT INTO product_stocks (product_id, warehouse_id, quantity)
             VALUES (?, ?, 0)
             ON DUPLICATE KEY UPDATE product_id = product_id'
        );
        $insert->execute([$productId, $warehouseId]);
    }

    public function decrement(int $productId, int $warehouseId, int $qty): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE product_stocks SET quantity = quantity - ? WHERE product_id = ? AND warehouse_id = ?'
        );
        $stmt->execute([$qty, $productId, $warehouseId]);
    }

    public function countLowStock(): int
    {
        $sql = 'SELECT COUNT(*) FROM (
                    SELECT p.id
                    FROM products p
                    LEFT JOIN product_stocks ps ON ps.product_id = p.id
                    WHERE p.is_active = 1
                    GROUP BY p.id, p.reorder_point
                    HAVING COALESCE(SUM(ps.quantity), 0) < p.reorder_point
                ) t';
        $stmt = $this->pdo->query($sql);

        return $stmt ? (int) $stmt->fetchColumn() : 0;
    }

    public function lowStockList(): array
    {
        $sql = 'SELECT p.id AS product_id, p.sku, p.name, p.reorder_point,
                       COALESCE(SUM(ps.quantity), 0) AS total
                FROM products p
                LEFT JOIN product_stocks ps ON ps.product_id = p.id
                WHERE p.is_active = 1
                GROUP BY p.id, p.sku, p.name, p.reorder_point
                HAVING total < p.reorder_point
                ORDER BY total ASC';
        $stmt = $this->pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll() : [];

        return array_map(static fn ($r) => [
            'product_id' => (int) $r['product_id'],
            'sku' => (string) $r['sku'],
            'name' => (string) $r['name'],
            'total' => (int) $r['total'],
            'reorder_point' => (int) $r['reorder_point'],
        ], $rows);
    }
}
