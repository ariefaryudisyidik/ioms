<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\MySqlSupplierRepository;
use App\Service\SupplierService;

final class SupplierController extends CrudController
{
    protected const VIEW = 'supplier';
    protected const BASE_URL = '/suppliers';
    protected const ENTITY_KEY = 'supplier';
    protected const LIST_KEY = 'suppliers';

    protected function service(): SupplierService
    {
        return new SupplierService(new MySqlSupplierRepository($this->pdo()));
    }

    public function deactivate(array $params): void
    {
        $this->remove($params);
    }
}
