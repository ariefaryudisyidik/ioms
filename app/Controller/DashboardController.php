<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Repository\MySqlProductRepository;
use App\Repository\MySqlProductStockRepository;
use App\Repository\MySqlPurchaseOrderRepository;
use App\Repository\MySqlSalesOrderRepository;
use App\Service\DashboardService;

final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        if (Auth::requireLogin()) {
            return;
        }

        $this->handle(function () {
            $service = new DashboardService(
                new MySqlProductRepository($this->pdo()),
                new MySqlProductStockRepository($this->pdo()),
                new MySqlSalesOrderRepository($this->pdo()),
                new MySqlPurchaseOrderRepository($this->pdo()),
            );

            $user = Auth::user();
            $summary = $service->summaryFor($user['role'], $user['id']);

            $this->render('dashboard.index', ['summary' => $summary]);
        }, false, '/dashboard');
    }
}
