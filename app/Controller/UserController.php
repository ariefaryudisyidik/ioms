<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\MySqlUserRepository;
use App\Service\UserService;

final class UserController extends CrudController
{
    protected const VIEW = 'user';
    protected const BASE_URL = '/users';
    protected const ENTITY_KEY = 'user';
    protected const LIST_KEY = 'users';
    protected const INDEX_ROLES = ['Admin'];
    protected const CREATE_DATA = ['old' => []];

    protected function service(): UserService
    {
        return new UserService(new MySqlUserRepository($this->pdo()));
    }

    public function index(): void
    {
        if ($this->denyIndex()) {
            return;
        }
        $this->handle(fn () => $this->renderIndex(), false, self::BASE_URL);
    }

    public function deactivate(array $params): void
    {
        $this->remove($params);
    }
}
