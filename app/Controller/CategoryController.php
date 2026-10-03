<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Repository\MySqlCategoryRepository;
use App\Service\CategoryService;

final class CategoryController extends Controller
{
    private const BASE_URL = '/categories';

    private function service(): CategoryService
    {
        return new CategoryService(new MySqlCategoryRepository($this->pdo()));
    }

    public function index(): void
    {
        if (Auth::requireLogin()) {
            return;
        }
        $this->render('category.index', ['categories' => $this->service()->all()]);
    }

    public function create(): void
    {
        if (Auth::requireRole('Admin')) {
            return;
        }
        $this->render('category.create', []);
    }

    public function store(Request $request): void
    {
        if (Auth::requireRole('Admin')) {
            return;
        }
        $this->handle(function () use ($request) {
            $this->service()->create($request->all());
            $this->redirect(self::BASE_URL);
        }, false, '/categories/create');
    }

    public function edit(array $params): void
    {
        if (Auth::requireRole('Admin')) {
            return;
        }
        $category = $this->service()->find((int) $params['id']);
        if ($category === null) {
            $this->render('errors.404', [], 404);

            return;
        }
        $this->render('category.edit', ['category' => $category]);
    }

    public function update(Request $request, array $params): void
    {
        if (Auth::requireRole('Admin')) {
            return;
        }
        $id = (int) $params['id'];
        $this->handle(function () use ($request, $id) {
            $this->service()->update($id, $request->all());
            $this->redirect(self::BASE_URL);
        }, false, "/categories/{$id}/edit");
    }

    public function destroy(array $params): void
    {
        if (Auth::requireRole('Admin')) {
            return;
        }
        $id = (int) $params['id'];
        $this->handle(function () use ($id) {
            $this->service()->delete($id);
            $this->redirect(self::BASE_URL);
        }, false, self::BASE_URL);
    }
}
