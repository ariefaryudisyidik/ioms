<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Warehouse;

/**
 * In-memory fake for WarehouseRepositoryInterface (unit tests).
 */
final class InMemoryWarehouseRepository implements WarehouseRepositoryInterface
{
    /** @var array<int,Warehouse> */
    private array $warehouses = [];
    private int $nextId = 1;

    public function findById(int $id): ?Warehouse
    {
        return $this->warehouses[$id] ?? null;
    }

    public function all(bool $onlyActive = false): array
    {
        $list = array_values($this->warehouses);

        return $onlyActive ? array_values(array_filter($list, static fn (Warehouse $w) => $w->isActive)) : $list;
    }

    public function save(Warehouse $warehouse): Warehouse
    {
        if ($warehouse->id === null) {
            $warehouse->id = $this->nextId++;
        }
        $this->warehouses[$warehouse->id] = $warehouse;

        return $warehouse;
    }
}
