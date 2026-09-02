<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Repository\MySqlProductRepository;
use App\Repository\MySqlProductStockRepository;
use App\Repository\MySqlPurchaseOrderRepository;
use App\Repository\MySqlStockLedgerRepository;
use App\Repository\MySqlSupplierRepository;
use App\Repository\MySqlWarehouseRepository;
use App\Service\PurchaseOrderService;

final class PurchaseOrderController extends Controller
{
    private function service(): PurchaseOrderService
    {
        return new PurchaseOrderService(
            new MySqlPurchaseOrderRepository($this->pdo()),
            new MySqlProductStockRepository($this->pdo()),
            new MySqlStockLedgerRepository($this->pdo()),
            $this->pdo(),
        );
    }

    public function index(Request $request): void
    {
        if (Auth::requireLogin()) {
            return;
        }

        $this->handle(function () use ($request) {
            $repo = new MySqlPurchaseOrderRepository($this->pdo());
            $filters = [
                'status' => $request->query('status', ''),
                'supplier_id' => (int) $request->query('supplier_id', 0) ?: null,
                'limit' => 10,
                'offset' => (max(1, (int) $request->query('page', 1)) - 1) * 10,
            ];
            $items = $repo->search($filters);
            $total = $repo->countSearch($filters);

            $this->render('purchase_order.index', [
                'items' => $items,
                'total' => $total,
                'page' => (int) $request->query('page', 1),
                'filters' => $filters,
                'suppliers' => (new MySqlSupplierRepository($this->pdo()))->all(),
            ]);
        }, false, '/purchase-orders');
    }

    public function show(Request $request, array $params): void
    {
        if (Auth::requireLogin()) {
            return;
        }
        $repo = new MySqlPurchaseOrderRepository($this->pdo());
        $po = $repo->findWithItems((int) $params['id']);
        if ($po === null) {
            $this->render('errors.404', [], 404);

            return;
        }
        $this->render('purchase_order.show', [
            'po' => $po,
            'products' => (new MySqlProductRepository($this->pdo()))->all(true),
        ]);
    }

    public function create(Request $request): void
    {
        if (Auth::requireRole('/dashboard', 'Admin', 'WarehouseStaff')) {
            return;
        }
        $this->render('purchase_order.create', [
            'suppliers' => (new MySqlSupplierRepository($this->pdo()))->all(true),
            'warehouses' => (new MySqlWarehouseRepository($this->pdo()))->all(true),
            'products' => (new MySqlProductRepository($this->pdo()))->all(true),
        ]);
    }

    public function store(Request $request): void
    {
        if (Auth::requireRole('/dashboard', 'Admin', 'WarehouseStaff')) {
            return;
        }
        $this->handle(function () use ($request) {
            $po = $this->service()->create($request->all(), Auth::id());
            $this->redirect('/purchase-orders/' . $po->id);
        }, false, '/purchase-orders/create');
    }

    public function order(Request $request, array $params): void
    {
        if (Auth::requireRole('/dashboard', 'Admin', 'WarehouseStaff')) {
            return;
        }
        $id = (int) $params['id'];
        $this->handle(function () use ($id) {
            $this->service()->transitionTo($id, 'Ordered');
            $this->redirect('/purchase-orders/' . $id);
        }, false, '/purchase-orders/' . $id);
    }

    public function cancel(Request $request, array $params): void
    {
        if (Auth::requireRole('/dashboard', 'Admin', 'WarehouseStaff')) {
            return;
        }
        $id = (int) $params['id'];
        $this->handle(function () use ($id) {
            $this->service()->transitionTo($id, 'Cancelled');
            $this->redirect('/purchase-orders/' . $id);
        }, false, '/purchase-orders/' . $id);
    }

    public function receiveForm(Request $request, array $params): void
    {
        if (Auth::requireRole('/dashboard', 'Admin', 'WarehouseStaff')) {
            return;
        }
        $id = (int) $params['id'];
        $repo = new MySqlPurchaseOrderRepository($this->pdo());
        $po = $repo->findWithItems($id);
        if ($po === null) {
            $this->render('errors.404', [], 404);

            return;
        }
        $this->render('purchase_order.receive', ['po' => $po]);
    }

    public function receive(Request $request, array $params): void
    {
        if (Auth::requireRole('/dashboard', 'Admin', 'WarehouseStaff')) {
            return;
        }
        $id = (int) $params['id'];
        $this->handle(function () use ($request, $id) {
            $items = (array) $request->input('items', []);
            $normalized = [];
            foreach ($items as $itemId => $qty) {
                $normalized[] = ['item_id' => (int) $itemId, 'qty' => (int) $qty];
            }
            $this->service()->receiveGoods($id, $normalized, Auth::id());
            $this->redirect('/purchase-orders/' . $id);
        }, false, '/purchase-orders/' . $id . '/receive');
    }
}
