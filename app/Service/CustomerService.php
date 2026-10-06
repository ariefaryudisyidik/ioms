<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Repository\CustomerRepositoryInterface;
use App\Service\Concerns\ListsEntities;
use App\Service\Exception\ValidationException;

final class CustomerService
{
    use ListsEntities;

    public function __construct(private CustomerRepositoryInterface $customers)
    {
    }

    public function all(bool $onlyActive = false): array
    {
        return $this->customers->all($onlyActive);
    }

    /** @return array<string, callable(object):mixed> */
    protected function listColumns(): array
    {
        return [
            'name' => static fn (Customer $e): string => $e->name,
            'contact' => static fn (Customer $e): string => (string) $e->contact,
            'address' => static fn (Customer $e): string => (string) $e->address,
            'status' => static fn (Customer $e): bool => $e->isActive,
        ];
    }

    /** @return list<string> */
    protected function searchColumns(): array
    {
        return ['name', 'contact', 'address'];
    }

    protected function statusColumn(): ?string
    {
        return 'status';
    }

    public function find(int $id): ?Customer
    {
        return $this->customers->findById($id);
    }

    public function create(array $data): Customer
    {
        $errors = $this->validate($data);
        if ($errors) {
            throw new ValidationException($errors);
        }

        $customer = new Customer(null, trim($data['name']), $data['contact'] ?? null, $data['address'] ?? null, (bool) ($data['is_active'] ?? true));

        return $this->customers->save($customer);
    }

    public function update(int $id, array $data): Customer
    {
        $customer = $this->customers->findById($id);
        if ($customer === null) {
            throw new ValidationException(['id' => 'Customer not found.']);
        }

        $errors = $this->validate($data);
        if ($errors) {
            throw new ValidationException($errors);
        }

        $customer->name = trim($data['name']);
        $customer->contact = $data['contact'] ?? $customer->contact;
        $customer->address = $data['address'] ?? $customer->address;
        $customer->isActive = (bool) ($data['is_active'] ?? $customer->isActive);

        return $this->customers->save($customer);
    }

    public function deactivate(int $id): void
    {
        $customer = $this->customers->findById($id);
        if ($customer === null) {
            throw new ValidationException(['id' => 'Customer not found.']);
        }
        $customer->isActive = false;
        $this->customers->save($customer);
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
