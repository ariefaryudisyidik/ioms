<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Supplier;

interface SupplierRepositoryInterface
{
    public function findById(int $id): ?Supplier;

    /** @return Supplier[] */
    public function all(bool $onlyActive = false): array;

    public function save(Supplier $supplier): Supplier;
}
