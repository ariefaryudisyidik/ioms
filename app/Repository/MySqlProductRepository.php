<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use PDO;

final class MySqlProductRepository implements ProductRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?Product
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, sku, name, category_id, unit, purchase_price, selling_price, reorder_point, image_path, is_active
             FROM products WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? Product::fromRow($row) : null;
    }

    public function findBySku(string $sku): ?Product
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, sku, name, category_id, unit, purchase_price, selling_price, reorder_point, image_path, is_active
             FROM products WHERE sku = ?'
        );
        $stmt->execute([$sku]);
        $row = $stmt->fetch();

        return $row ? Product::fromRow($row) : null;
    }

    public function skuExists(string $sku, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM products WHERE sku = ? AND id <> ?');
            $stmt->execute([$sku, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM products WHERE sku = ?');
            $stmt->execute([$sku]);
        }

        return ((int) $stmt->fetchColumn()) > 0;
    }

    private function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(p.name LIKE ? OR p.sku LIKE ?)';
            $like = '%' . addcslashes((string) $filters['search'], '%_\\') . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'p.category_id = ?';
            $params[] = (int) $filters['category_id'];
        }
        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $where[] = 'p.is_active = ?';
            $params[] = (int) $filters['is_active'];
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'low') {
                $where[] = '(SELECT COALESCE(SUM(ps.quantity),0) FROM product_stocks ps WHERE ps.product_id = p.id) < p.reorder_point';
            } elseif ($filters['status'] === 'normal') {
                $where[] = '(SELECT COALESCE(SUM(ps.quantity),0) FROM product_stocks ps WHERE ps.product_id = p.id) >= p.reorder_point';
            }
        }

        return [$where, $params];
    }

    public function search(array $filters = []): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = 'SELECT p.id, p.sku, p.name, p.category_id, p.unit, p.purchase_price, p.selling_price,
                       p.reorder_point, p.image_path, p.is_active
                FROM products p';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= match ($filters['sort'] ?? 'name_asc') {
            'name_desc' => ' ORDER BY p.name DESC',
            'unit_asc' => ' ORDER BY p.unit ASC, p.name ASC',
            'unit_desc' => ' ORDER BY p.unit DESC, p.name ASC',
            'status_asc' => ' ORDER BY p.is_active ASC, p.name ASC',
            'status_desc' => ' ORDER BY p.is_active DESC, p.name ASC',
            'sku_asc' => ' ORDER BY p.sku ASC',
            'sku_desc' => ' ORDER BY p.sku DESC',
            'purchase_price_asc' => ' ORDER BY p.purchase_price ASC, p.name ASC',
            'purchase_price_desc' => ' ORDER BY p.purchase_price DESC, p.name ASC',
            'selling_price_asc' => ' ORDER BY p.selling_price ASC, p.name ASC',
            'selling_price_desc' => ' ORDER BY p.selling_price DESC, p.name ASC',
            'reorder_point_asc' => ' ORDER BY p.reorder_point ASC, p.name ASC',
            'reorder_point_desc' => ' ORDER BY p.reorder_point DESC, p.name ASC',
            default => ' ORDER BY p.name ASC',
        };

        $limit = (int) ($filters['limit'] ?? 10);
        $offset = (int) ($filters['offset'] ?? 0);
        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(fn ($r) => Product::fromRow($r), $stmt->fetchAll());
    }

    public function countSearch(array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = 'SELECT COUNT(*) FROM products p';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function all(bool $onlyActive = false): array
    {
        $sql = 'SELECT id, sku, name, category_id, unit, purchase_price, selling_price, reorder_point, image_path, is_active
                FROM products' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY name ASC';
        $stmt = $this->pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll() : [];

        return array_map(fn ($r) => Product::fromRow($r), $rows);
    }

    public function save(Product $product): Product
    {
        if ($product->id === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO products (sku, name, category_id, unit, purchase_price, selling_price, reorder_point, image_path, is_active)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $product->sku, $product->name, $product->categoryId, $product->unit,
                $product->purchasePrice, $product->sellingPrice, $product->reorderPoint,
                $product->imagePath, (int) $product->isActive,
            ]);
            $product->id = (int) $this->pdo->lastInsertId();

            return $product;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE products SET sku=?, name=?, category_id=?, unit=?, purchase_price=?, selling_price=?, reorder_point=?, image_path=?, is_active=? WHERE id=?'
        );
        $stmt->execute([
            $product->sku, $product->name, $product->categoryId, $product->unit,
            $product->purchasePrice, $product->sellingPrice, $product->reorderPoint,
            $product->imagePath, (int) $product->isActive, $product->id,
        ]);

        return $product;
    }

    public function isUsedInOrders(int $productId): bool
    {
        $stmt = $this->pdo->prepare(
            '(SELECT id FROM purchase_order_items WHERE product_id = ? LIMIT 1)
             UNION
             (SELECT id FROM sales_order_items WHERE product_id = ? LIMIT 1)'
        );
        $stmt->execute([$productId, $productId]);

        return $stmt->fetch() !== false;
    }
}
