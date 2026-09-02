<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Supplier;
use App\Repository\SupplierRepositoryInterface;
use App\Service\Exception\ValidationException;

final class SupplierService
{
    public function __construct(private SupplierRepositoryInterface $suppliers)
    {
    }

    public function all(bool $onlyActive = false): array
    {
        return $this->suppliers->all($onlyActive);
    }

    public function find(int $id): ?Supplier
    {
        return $this->suppliers->findById($id);
    }

    public function create(array $data): Supplier
    {
        $errors = $this->validate($data);
        if ($errors) {
            throw new ValidationException($errors);
        }

        $supplier = new Supplier(null, trim($data['name']), $data['contact'] ?? null, $data['address'] ?? null, (bool) ($data['is_active'] ?? true));

        return $this->suppliers->save($supplier);
    }

    public function update(int $id, array $data): Supplier
    {
        $supplier = $this->suppliers->findById($id);
        if ($supplier === null) {
            throw new ValidationException(['id' => 'Supplier not found.']);
        }

        $errors = $this->validate($data);
        if ($errors) {
            throw new ValidationException($errors);
        }

        $supplier->name = trim($data['name']);
        $supplier->contact = $data['contact'] ?? $supplier->contact;
        $supplier->address = $data['address'] ?? $supplier->address;
        $supplier->isActive = (bool) ($data['is_active'] ?? $supplier->isActive);

        return $this->suppliers->save($supplier);
    }

    public function deactivate(int $id): void
    {
        $supplier = $this->suppliers->findById($id);
        if ($supplier === null) {
            throw new ValidationException(['id' => 'Supplier not found.']);
        }
        $supplier->isActive = false;
        $this->suppliers->save($supplier);
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
