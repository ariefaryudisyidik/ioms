<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductStock;
use PDO;

interface ProductStockRepositoryInterface
{
    public function find(int $productId, int $warehouseId): ?ProductStock;

    /** @return ProductStock[] */
    public function findByProduct(int $productId): array;

    /** @return ProductStock[] */
    public function findByWarehouse(int $warehouseId): array;

    public function totalForProduct(int $productId): int;

    /**
     * Lock the row for update within an already-open transaction and
     * return the current quantity (0 if the row does not yet exist).
     * Must be called with an active transaction on the given PDO.
     */
    public function lockForUpdate(PDO $pdo, int $productId, int $warehouseId): int;

    /**
     * Increase stock (upsert). Used for goods receipt.
     */
    public function increment(int $productId, int $warehouseId, int $qty): void;

    /**
     * Decrease stock. Assumes sufficient quantity has already been verified
     * (e.g. via lockForUpdate within the same transaction).
     */
    public function decrement(int $productId, int $warehouseId, int $qty): void;

    /** @return int count of products whose total stock is below reorder point */
    public function countLowStock(): int;

    /**
     * @return array<int,array{product_id:int,sku:string,name:string,total:int,reorder_point:int}>
     */
    public function lowStockList(): array;
}
