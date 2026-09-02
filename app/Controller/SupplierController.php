<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Repository\MySqlSupplierRepository;
use App\Service\SupplierService;

final class SupplierController extends Controller
{
    private function service(): SupplierService
    {
        return new SupplierService(new MySqlSupplierRepository($this->pdo()));
    }

    public function index(Request $request): void
    {
        if (Auth::requireLogin()) {
            return;
        }
        $this->render('supplier.index', ['suppliers' => $this->service()->all()]);
    }

    public function create(Request $request): void
    {
        if (Auth::requireRole('/dashboard', 'Admin')) {
            return;
        }
        $this->render('supplier.create', []);
    }

    public function store(Request $request): void
    {
        if (Auth::requireRole('/dashboard', 'Admin')) {
            return;
        }
        $this->handle(function () use ($request) {
            $this->service()->create($request->all());
            $this->redirect('/suppliers');
        }, false, '/suppliers/create');
    }

    public function edit(Request $request, array $params): void
    {
        if (Auth::requireRole('/dashboard', 'Admin')) {
            return;
        }
        $supplier = $this->service()->find((int) $params['id']);
        if ($supplier === null) {
            $this->render('errors.404', [], 404);

            return;
        }
        $this->render('supplier.edit', ['supplier' => $supplier]);
    }

    public function update(Request $request, array $params): void
    {
        if (Auth::requireRole('/dashboard', 'Admin')) {
            return;
        }
        $id = (int) $params['id'];
        $this->handle(function () use ($request, $id) {
            $this->service()->update($id, $request->all());
            $this->redirect('/suppliers');
        }, false, "/suppliers/{$id}/edit");
    }

    public function deactivate(Request $request, array $params): void
    {
        if (Auth::requireRole('/dashboard', 'Admin')) {
            return;
        }
        $id = (int) $params['id'];
        $this->handle(function () use ($id) {
            $this->service()->deactivate($id);
            $this->redirect('/suppliers');
        }, false, '/suppliers');
    }
}
