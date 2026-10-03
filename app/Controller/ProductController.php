<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Repository\MySqlCategoryRepository;
use App\Repository\MySqlProductRepository;
use App\Service\ProductService;

final class ProductController extends Controller
{
    private const BASE_URL = '/products';

    private function service(): ProductService
    {
        return new ProductService(
            new MySqlProductRepository($this->pdo()),
            new MySqlCategoryRepository($this->pdo()),
            $this->uploadDir(),
        );
    }

    private function uploadDir(): string
    {
        $config = require dirname(__DIR__, 2) . '/config/config.php';

        return dirname(__DIR__, 2) . '/' . ltrim((string) $config['app']['upload_dir'], '/');
    }

    public function index(Request $request): void
    {
        if (Auth::requireLogin()) {
            return;
        }

        $this->handle(function () use ($request) {
            $filters = [
                'search' => $request->query('search', ''),
                'category_id' => (int) $request->query('category_id', 0) ?: null,
                'status' => $request->query('status', ''),
                'sort' => $request->query('sort', 'name_asc'),
            ];
            $page = (int) $request->query('page', 1);

            $result = $this->service()->paginate($filters, $page, 10);

            $this->render('product.index', [
                'result' => $result,
                'filters' => $filters,
                'categories' => (new MySqlCategoryRepository($this->pdo()))->all(),
            ]);
        }, false, self::BASE_URL);
    }

    public function show(array $params): void
    {
        if (Auth::requireLogin()) {
            return;
        }
        $product = $this->service()->find((int) $params['id']);
        if ($product === null) {
            $this->render('errors.404', [], 404);

            return;
        }
        $this->render('product.show', ['product' => $product]);
    }

    public function create(): void
    {
        if (Auth::requireRole('Admin')) {
            return;
        }
        $this->render('product.create', ['categories' => (new MySqlCategoryRepository($this->pdo()))->all()]);
    }

    public function store(Request $request): void
    {
        if (Auth::requireRole('Admin')) {
            return;
        }
        $this->handle(function () use ($request) {
            $this->service()->create($request->all(), $request->file('image'));
            $this->redirect(self::BASE_URL);
        }, false, '/products/create');
    }

    public function edit(array $params): void
    {
        if (Auth::requireRole('Admin')) {
            return;
        }
        $product = $this->service()->find((int) $params['id']);
        if ($product === null) {
            $this->render('errors.404', [], 404);

            return;
        }
        $this->render('product.edit', [
            'product' => $product,
            'categories' => (new MySqlCategoryRepository($this->pdo()))->all(),
        ]);
    }

    public function update(Request $request, array $params): void
    {
        if (Auth::requireRole('Admin')) {
            return;
        }
        $id = (int) $params['id'];
        $this->handle(function () use ($request, $id) {
            $this->service()->update($id, $request->all(), $request->file('image'));
            $this->redirect(self::BASE_URL);
        }, false, "/products/{$id}/edit");
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
