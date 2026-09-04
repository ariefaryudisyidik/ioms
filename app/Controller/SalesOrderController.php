<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Repository\MySqlCustomerRepository;
use App\Repository\MySqlProductRepository;
use App\Repository\MySqlProductStockRepository;
use App\Repository\MySqlSalesOrderRepository;
use App\Repository\MySqlStockLedgerRepository;
use App\Repository\MySqlWarehouseRepository;
use App\Service\Exception\AuthorizationException;
use App\Service\SalesOrderService;

final class SalesOrderController extends Controller
{
    private function service(): SalesOrderService
    {
        return new SalesOrderService(
            new MySqlSalesOrderRepository($this->pdo()),
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
            $repo = new MySqlSalesOrderRepository($this->pdo());
            $filters = [
                'status' => $request->query('status', ''),
                'sort' => $request->query('sort', 'desc') === 'asc' ? 'asc' : 'desc',
                'limit' => 10,
                'offset' => (max(1, (int) $request->query('page', 1)) - 1) * 10,
            ];
            // Sales users only see their own orders; Admin/WarehouseStaff see all.
            if (Auth::role() === 'Sales') {
                $filters['created_by'] = Auth::id();
            }

            $items = $repo->search($filters);
            $total = $repo->countSearch($filters);

            $this->render('sales_order.index', [
                'items' => $items,
                'total' => $total,
                'page' => (int) $request->query('page', 1),
                'filters' => $filters,
            ]);
        }, false, '/sales-orders');
    }

    public function show(Request $request, array $params): void
    {
        if (Auth::requireLogin()) {
            return;
        }
        $repo = new MySqlSalesOrderRepository($this->pdo());
        $so = $repo->findWithItems((int) $params['id']);
        if ($so === null) {
            $this->render('errors.404', [], 404);

            return;
        }
        $this->render('sales_order.show', ['so' => $so]);
    }

    public function create(Request $request): void
    {
        if (Auth::requireRole('/dashboard', 'Admin', 'Sales')) {
            return;
        }
        $this->render('sales_order.create', [
            'customers' => (new MySqlCustomerRepository($this->pdo()))->all(true),
            'warehouses' => (new MySqlWarehouseRepository($this->pdo()))->all(true),
            'products' => (new MySqlProductRepository($this->pdo()))->all(true),
        ]);
    }

    public function store(Request $request): void
    {
        if (Auth::requireRole('/dashboard', 'Admin', 'Sales')) {
            return;
        }
        $this->handle(function () use ($request) {
            $so = $this->service()->create($request->all(), Auth::id());
            $this->redirect('/sales-orders/' . $so->id);
        }, false, '/sales-orders/create');
    }

    public function submit(Request $request, array $params): void
    {
        if (Auth::requireRole('/dashboard', 'Admin', 'Sales')) {
            return;
        }
        $id = (int) $params['id'];
        $this->handle(function () use ($id) {
            $this->service()->submitForApproval($id, Auth::id());
            $this->redirect('/sales-orders/' . $id);
        }, false, '/sales-orders/' . $id);
    }

    public function approve(Request $request, array $params): void
    {
        // Defense in depth: controller-level role check, but the service
        // remains the authoritative gatekeeper.
        if (Auth::requireRole('/dashboard', 'Admin')) {
            return;
        }
        $id = (int) $params['id'];
        $this->handle(function () use ($id) {
            $this->service()->approve($id, Auth::id(), Auth::role() ?? '');
            $this->redirect('/sales-orders/' . $id);
        }, false, '/sales-orders/' . $id);
    }

    public function reject(Request $request, array $params): void
    {
        if (Auth::requireRole('/dashboard', 'Admin')) {
            return;
        }
        $id = (int) $params['id'];
        $this->handle(function () use ($id) {
            $this->service()->reject($id, Auth::id(), Auth::role() ?? '');
            $this->redirect('/sales-orders/' . $id);
        }, false, '/sales-orders/' . $id);
    }

    public function cancel(Request $request, array $params): void
    {
        if (Auth::requireRole('/dashboard', 'Admin', 'Sales')) {
            return;
        }
        $id = (int) $params['id'];
        $this->handle(function () use ($id) {
            $this->service()->cancel($id);
            $this->redirect('/sales-orders/' . $id);
        }, false, '/sales-orders/' . $id);
    }

    public function fulfill(Request $request, array $params): void
    {
        if (Auth::requireRole('/dashboard', 'Admin', 'WarehouseStaff')) {
            return;
        }
        $id = (int) $params['id'];
        $this->handle(function () use ($id) {
            $this->service()->fulfill($id, Auth::id());
            $this->redirect('/sales-orders/' . $id);
        }, false, '/sales-orders/' . $id);
    }
}
