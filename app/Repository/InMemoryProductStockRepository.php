<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductStock;

/**
 * In-memory fake for ProductStockRepositoryInterface. Locking is simulated
 * with a simple in-process flag sufficient for unit-testing transition and
 * insufficient-stock logic (no real FOR UPDATE semantics).
 */
final class InMemoryProductStockRepository implements ProductStockRepositoryInterface
{
    /** @var array<string,int> keyed by "productId:warehouseId" */
    private array $stocks = [];

    /**
     * Optional reference to a product repository so countLowStock() /
     * lowStockList() can be exercised meaningfully in unit tests (mirrors
     * the reorder-point comparison the MySQL implementation does in SQL).
     */
    public function __construct(private ?ProductRepositoryInterface $products = null)
    {
    }

    private function key(int $productId, int $warehouseId): string
    {
        return $productId . ':' . $warehouseId;
    }

    public function setQuantity(int $productId, int $warehouseId, int $qty): void
    {
        $this->stocks[$this->key($productId, $warehouseId)] = $qty;
    }

    public function find(int $productId, int $warehouseId): ?ProductStock
    {
        $key = $this->key($productId, $warehouseId);
        if (!isset($this->stocks[$key])) {
            return null;
        }

        return new ProductStock(null, $productId, $warehouseId, $this->stocks[$key]);
    }

    public function findByProduct(int $productId): array
    {
        $result = [];
        foreach ($this->stocks as $key => $qty) {
            [$pid, $wid] = array_map('intval', explode(':', $key));
            if ($pid === $productId) {
                $result[] = new ProductStock(null, $pid, $wid, $qty);
            }
        }

        return $result;
    }

    public function findByWarehouse(int $warehouseId): array
    {
        $result = [];
        foreach ($this->stocks as $key => $qty) {
            [$pid, $wid] = array_map('intval', explode(':', $key));
            if ($wid === $warehouseId) {
                $result[] = new ProductStock(null, $pid, $wid, $qty);
            }
        }

        return $result;
    }

    public function totalForProduct(int $productId): int
    {
        $total = 0;
        foreach ($this->stocks as $key => $qty) {
            [$pid] = array_map('intval', explode(':', $key));
            if ($pid === $productId) {
                $total += $qty;
            }
        }

        return $total;
    }

    public function lockForUpdate(int $productId, int $warehouseId): int
    {
        return $this->stocks[$this->key($productId, $warehouseId)] ?? 0;
    }

    public function increment(int $productId, int $warehouseId, int $qty): void
    {
        $key = $this->key($productId, $warehouseId);
        $this->stocks[$key] = ($this->stocks[$key] ?? 0) + $qty;
    }

    public function decrement(int $productId, int $warehouseId, int $qty): void
    {
        $key = $this->key($productId, $warehouseId);
        $this->stocks[$key] = ($this->stocks[$key] ?? 0) - $qty;
    }

    public function totalInventoryValue(): float
    {
        $value = 0.0;
        foreach ($this->stocks as $key => $qty) {
            $product = $this->products?->findById((int) explode(':', $key)[0]);
            $value += $product !== null && $product->isActive ? $qty * $product->purchasePrice : 0.0;
        }

        return $value;
    }

    public function totalRetailValue(): float
    {
        $value = 0.0;
        foreach ($this->stocks as $key => $qty) {
            $product = $this->products?->findById((int) explode(':', $key)[0]);
            $value += $product !== null && $product->isActive ? $qty * $product->sellingPrice : 0.0;
        }

        return $value;
    }

    public function countLowStock(): int
    {
        return count($this->lowStockList());
    }

    public function lowStockList(): array
    {
        if ($this->products === null) {
            return [];
        }

        $result = [];
        foreach ($this->products->all(true) as $product) {
            $total = $this->totalForProduct($product->id);
            if ($total < $product->reorderPoint) {
                $result[] = [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'total' => $total,
                    'reorder_point' => $product->reorderPoint,
                ];
            }
        }

        return $result;
    }
}
