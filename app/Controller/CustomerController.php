<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Repository\MySqlCustomerRepository;
use App\Service\CustomerService;

final class CustomerController extends Controller
{
    private function service(): CustomerService
    {
        return new CustomerService(new MySqlCustomerRepository($this->pdo()));
    }

    public function index(Request $request): void
    {
        if (Auth::requireLogin()) {
            return;
        }
        $this->render('customer.index', ['customers' => $this->service()->all()]);
    }

    public function create(Request $request): void
    {
        if (Auth::requireRole('/dashboard', 'Admin', 'Sales')) {
            return;
        }
        $this->render('customer.create', []);
    }

    public function store(Request $request): void
    {
        if (Auth::requireRole('/dashboard', 'Admin', 'Sales')) {
            return;
        }
        $this->handle(function () use ($request) {
            $this->service()->create($request->all());
            $this->redirect('/customers');
        }, false, '/customers/create');
    }

    public function edit(Request $request, array $params): void
    {
        if (Auth::requireRole('/dashboard', 'Admin', 'Sales')) {
            return;
        }
        $customer = $this->service()->find((int) $params['id']);
        if ($customer === null) {
            $this->render('errors.404', [], 404);

            return;
        }
        $this->render('customer.edit', ['customer' => $customer]);
    }

    public function update(Request $request, array $params): void
    {
        if (Auth::requireRole('/dashboard', 'Admin', 'Sales')) {
            return;
        }
        $id = (int) $params['id'];
        $this->handle(function () use ($request, $id) {
            $this->service()->update($id, $request->all());
            $this->redirect('/customers');
        }, false, "/customers/{$id}/edit");
    }

    public function deactivate(Request $request, array $params): void
    {
        if (Auth::requireRole('/dashboard', 'Admin')) {
            return;
        }
        $id = (int) $params['id'];
        $this->handle(function () use ($id) {
            $this->service()->deactivate($id);
            $this->redirect('/customers');
        }, false, '/customers');
    }
}
