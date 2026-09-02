<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;

interface CustomerRepositoryInterface
{
    public function findById(int $id): ?Customer;

    /** @return Customer[] */
    public function all(bool $onlyActive = false): array;

    public function save(Customer $customer): Customer;
}
