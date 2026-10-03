<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\MySqlCategoryRepository;
use App\Service\CategoryService;

final class CategoryController extends CrudController
{
    protected const VIEW = 'category';
    protected const BASE_URL = '/categories';
    protected const ENTITY_KEY = 'category';
    protected const LIST_KEY = 'categories';
    protected const REMOVE_METHOD = 'delete';

    protected function service(): CategoryService
    {
        return new CategoryService(new MySqlCategoryRepository($this->pdo()));
    }

    public function destroy(array $params): void
    {
        $this->remove($params);
    }
}
