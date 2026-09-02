<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Warehouse;
use App\Repository\WarehouseRepositoryInterface;
use App\Service\Exception\ValidationException;

final class WarehouseService
{
    public function __construct(private WarehouseRepositoryInterface $warehouses)
    {
    }

    public function all(bool $onlyActive = false): array
    {
        return $this->warehouses->all($onlyActive);
    }

    public function find(int $id): ?Warehouse
    {
        return $this->warehouses->findById($id);
    }

    public function create(array $data): Warehouse
    {
        $errors = $this->validate($data);
        if ($errors) {
            throw new ValidationException($errors);
        }

        $warehouse = new Warehouse(null, trim($data['name']), trim((string) ($data['location'] ?? '')), (bool) ($data['is_active'] ?? true));

        return $this->warehouses->save($warehouse);
    }

    public function update(int $id, array $data): Warehouse
    {
        $warehouse = $this->warehouses->findById($id);
        if ($warehouse === null) {
            throw new ValidationException(['id' => 'Warehouse not found.']);
        }

        $errors = $this->validate($data);
        if ($errors) {
            throw new ValidationException($errors);
        }

        $warehouse->name = trim($data['name']);
        $warehouse->location = trim((string) ($data['location'] ?? $warehouse->location));
        $warehouse->isActive = (bool) ($data['is_active'] ?? $warehouse->isActive);

        return $this->warehouses->save($warehouse);
    }

    public function deactivate(int $id): void
    {
        $warehouse = $this->warehouses->findById($id);
        if ($warehouse === null) {
            throw new ValidationException(['id' => 'Warehouse not found.']);
        }
        $warehouse->isActive = false;
        $this->warehouses->save($warehouse);
    }

    private function validate(array $data): array
    {
        $errors = [];
        if (trim((string) ($data['name'] ?? '')) === '') {
            $errors['name'] = 'Name is required.';
        }

        return $errors;
    }
}
