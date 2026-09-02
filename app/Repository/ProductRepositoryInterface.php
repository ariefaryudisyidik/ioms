<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;

interface ProductRepositoryInterface
{
    public function findById(int $id): ?Product;

    public function findBySku(string $sku): ?Product;

    public function skuExists(string $sku, ?int $excludeId = null): bool;

    /**
     * @param array{search?:string,category_id?:int,status?:string,is_active?:bool,sort?:string,limit?:int,offset?:int} $filters
     * @return Product[]
     */
    public function search(array $filters = []): array;

    public function countSearch(array $filters = []): int;

    /** @return Product[] */
    public function all(bool $onlyActive = false): array;

    public function save(Product $product): Product;

    public function isUsedInOrders(int $productId): bool;
}
