<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\MySqlCustomerRepository;
use App\Service\CustomerService;

final class CustomerController extends CrudController
{
    protected const VIEW = 'customer';
    protected const BASE_URL = '/customers';
    protected const ENTITY_KEY = 'customer';
    protected const LIST_KEY = 'customers';
    protected const WRITE_ROLES = ['Admin', 'Sales'];

    protected function service(): CustomerService
    {
        return new CustomerService(new MySqlCustomerRepository($this->pdo()));
    }

    public function deactivate(array $params): void
    {
        $this->remove($params);
    }
}
