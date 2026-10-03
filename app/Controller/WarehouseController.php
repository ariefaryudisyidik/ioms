<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Repository\MySqlWarehouseRepository;
use App\Service\WarehouseService;

final class WarehouseController extends Controller
{
    private const BASE_URL = '/warehouses';

    private function service(): WarehouseService
    {
        return new WarehouseService(new MySqlWarehouseRepository($this->pdo()));
    }

    public function index(): void
    {
        if (Auth::requireLogin()) {
            return;
        }
        $this->render('warehouse.index', ['warehouses' => $this->service()->all()]);
    }

    public function create(): void
    {
        if (Auth::requireRole('Admin')) {
            return;
        }
        $this->render('warehouse.create', []);
    }

    public function store(Request $request): void
    {
        if (Auth::requireRole('Admin')) {
            return;
        }
        $this->handle(function () use ($request) {
            $this->service()->create($request->all());
            $this->redirect(self::BASE_URL);
        }, false, '/warehouses/create');
    }

    public function edit(array $params): void
    {
        if (Auth::requireRole('Admin')) {
            return;
        }
        $warehouse = $this->service()->find((int) $params['id']);
        if ($warehouse === null) {
            $this->render('errors.404', [], 404);

            return;
        }
        $this->render('warehouse.edit', ['warehouse' => $warehouse]);
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
        }, false, "/warehouses/{$id}/edit");
    }

    public function deactivate(array $params): void
    {
        if (Auth::requireRole('Admin')) {
            return;
        }
        $id = (int) $params['id'];
        $this->handle(function () use ($id) {
            $this->service()->deactivate($id);
            $this->redirect(self::BASE_URL);
        }, false, self::BASE_URL);
    }
}
