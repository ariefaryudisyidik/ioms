<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;

/**
 * In-memory fake used by unit tests so Service logic can be tested
 * without a real database connection.
 */
final class InMemoryProductRepository implements ProductRepositoryInterface
{
    /** @var Product[] */
    private array $products = [];
    private int $nextId = 1;
    /** @var int[] product ids considered "used in orders" for test scenarios */
    private array $usedIds = [];

    public function findById(int $id): ?Product
    {
        foreach ($this->products as $p) {
            if ($p->id === $id) {
                return $p;
            }
        }

        return null;
    }

    public function findBySku(string $sku): ?Product
    {
        foreach ($this->products as $p) {
            if ($p->sku === $sku) {
                return $p;
            }
        }

        return null;
    }

    public function skuExists(string $sku, ?int $excludeId = null): bool
    {
        foreach ($this->products as $p) {
            if ($p->sku === $sku && $p->id !== $excludeId) {
                return true;
            }
        }

        return false;
    }

    public function search(array $filters = []): array
    {
        return array_values($this->products);
    }

    public function countSearch(array $filters = []): int
    {
        return count($this->products);
    }

    public function all(bool $onlyActive = false): array
    {
        if (!$onlyActive) {
            return array_values($this->products);
        }

        return array_values(array_filter($this->products, fn ($p) => $p->isActive));
    }

    public function save(Product $product): Product
    {
        if ($product->id === null) {
            $product->id = $this->nextId++;
        }
        $this->products[$product->id] = $product;

        return $product;
    }

    public function isUsedInOrders(int $productId): bool
    {
        return in_array($productId, $this->usedIds, true);
    }

    public function markUsedInOrders(int $productId): void
    {
        $this->usedIds[] = $productId;
    }
}
