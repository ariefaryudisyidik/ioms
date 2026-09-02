<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Repository\MySqlPurchaseOrderRepository;
use App\Repository\MySqlSalesOrderRepository;
use App\Repository\MySqlStockLedgerRepository;
use App\Service\ReportService;

final class ReportController extends Controller
{
    private function service(): ReportService
    {
        return new ReportService(
            new MySqlStockLedgerRepository($this->pdo()),
            new MySqlSalesOrderRepository($this->pdo()),
            new MySqlPurchaseOrderRepository($this->pdo()),
        );
    }

    public function index(Request $request): void
    {
        if (Auth::requireLogin()) {
            return;
        }
        $this->render('report.index', []);
    }

    public function stockLedgerCsv(Request $request): void
    {
        if (Auth::requireLogin()) {
            return;
        }
        $this->handle(function () use ($request) {
            $csv = $this->service()->stockLedgerCsv(
                $request->query('date_from') ?: null,
                $request->query('date_to') ?: null,
            );
            Response::csv('stock_ledger.csv', $csv);
        }, false, '/reports');
    }

    public function orderStatusCsv(Request $request): void
    {
        if (Auth::requireLogin()) {
            return;
        }
        $this->handle(function () use ($request) {
            $type = (string) $request->query('type', 'sales');
            // Sales role only ever sees their own orders in the export.
            $restrict = Auth::role() === 'Sales' ? Auth::id() : null;

            $csv = $this->service()->orderStatusCsv(
                $type,
                $request->query('date_from') ?: null,
                $request->query('date_to') ?: null,
                $restrict,
            );
            Response::csv($type . '_orders.csv', $csv);
        }, false, '/reports');
    }
}
