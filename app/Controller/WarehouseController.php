<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\MySqlWarehouseRepository;
use App\Service\WarehouseService;

final class WarehouseController extends CrudController
{
    protected const VIEW = 'warehouse';
    protected const BASE_URL = '/warehouses';
    protected const ENTITY_KEY = 'warehouse';
    protected const LIST_KEY = 'warehouses';

    protected function service(): WarehouseService
    {
        return new WarehouseService(new MySqlWarehouseRepository($this->pdo()));
    }

    public function deactivate(array $params): void
    {
        $this->remove($params);
    }
}
