<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;

interface CategoryRepositoryInterface
{
    public function findById(int $id): ?Category;

    /** @return Category[] */
    public function all(): array;

    public function save(Category $category): Category;

    public function delete(int $id): bool;

    public function isUsedByProducts(int $categoryId): bool;
}
