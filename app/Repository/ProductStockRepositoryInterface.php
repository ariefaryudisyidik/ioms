<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductStock;

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
     * Must be called inside TransactionManagerInterface::run() so the lock is held until commit.
     */
    public function lockForUpdate(int $productId, int $warehouseId): int;

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
     * Value of all active products' stock at purchase price (sum of quantity x purchase_price).
     */
    public function totalInventoryValue(): float;

    /**
     * Value of all active products' stock at selling price (sum of quantity x selling_price).
     */
    public function totalRetailValue(): float;

    /**
     * @return array<int,array{product_id:int,sku:string,name:string,total:int,reorder_point:int}>
     */
    public function lowStockList(): array;
}
