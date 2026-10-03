<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
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

    public function update(Request $request, array $params): void
    {
        if (Auth::requireRole(...static::WRITE_ROLES)) {
            return;
        }
        $id = (int) $params['id'];
        $losesAccess = (string) $request->input('role', Auth::role()) !== Auth::role()
            || (string) $request->input('is_active', '1') === '0';
        if ($id === Auth::id() && $losesAccess) {
            $this->rejectOwnAccountChange(self::BASE_URL . '/' . $id . '/edit');

            return;
        }
        parent::update($request, $params);
    }

    public function deactivate(array $params): void
    {
        if (Auth::requireRole(...static::REMOVE_ROLES)) {
            return;
        }
        if ((int) $params['id'] === Auth::id()) {
            $this->rejectOwnAccountChange(self::BASE_URL);

            return;
        }
        $this->remove($params);
    }

    private function rejectOwnAccountChange(string $backUrl): void
    {
        Session::flash('error', 'You cannot deactivate your own account or change your own role.');
        $this->redirect($backUrl);
    }
}
