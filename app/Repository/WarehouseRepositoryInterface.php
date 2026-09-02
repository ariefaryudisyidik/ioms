<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Warehouse;

interface WarehouseRepositoryInterface
{
    public function findById(int $id): ?Warehouse;

    /** @return Warehouse[] */
    public function all(bool $onlyActive = false): array;

    public function save(Warehouse $warehouse): Warehouse;
}
