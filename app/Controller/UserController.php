<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Repository\MySqlUserRepository;
use App\Service\UserService;

final class UserController extends Controller
{
    private function service(): UserService
    {
        return new UserService(new MySqlUserRepository($this->pdo()));
    }

    public function index(Request $request): void
    {
        if (Auth::requireRole('/dashboard', 'Admin')) {
            return;
        }

        $this->handle(function () {
            $this->render('user.index', ['users' => $this->service()->all()]);
        }, false, '/users');
    }

    public function create(Request $request): void
    {
        if (Auth::requireRole('/dashboard', 'Admin')) {
            return;
        }
        $this->render('user.create', ['old' => []]);
    }

    public function store(Request $request): void
    {
        if (Auth::requireRole('/dashboard', 'Admin')) {
            return;
        }

        $this->handle(function () use ($request) {
            $this->service()->create($request->all());
            $this->redirect('/users');
        }, false, '/users/create');
    }

    public function edit(Request $request, array $params): void
    {
        if (Auth::requireRole('/dashboard', 'Admin')) {
            return;
        }

        $user = $this->service()->find((int) $params['id']);
        if ($user === null) {
            $this->render('errors.404', [], 404);

            return;
        }
        $this->render('user.edit', ['user' => $user]);
    }

    public function update(Request $request, array $params): void
    {
        if (Auth::requireRole('/dashboard', 'Admin')) {
            return;
        }

        $id = (int) $params['id'];
        $this->handle(function () use ($request, $id) {
            $this->service()->update($id, $request->all());
            $this->redirect('/users');
        }, false, "/users/{$id}/edit");
    }

    public function deactivate(Request $request, array $params): void
    {
        if (Auth::requireRole('/dashboard', 'Admin')) {
            return;
        }

        $id = (int) $params['id'];
        $this->handle(function () use ($id) {
            $this->service()->deactivate($id);
            $this->redirect('/users');
        }, false, '/users');
    }
}
