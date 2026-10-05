<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Entity\SalesOrder;
use App\Repository\MySqlProductRepository;
use App\Repository\MySqlProductStockRepository;
use App\Repository\PdoTransactionManager;
use App\Repository\MySqlSalesOrderRepository;
use App\Repository\MySqlStockLedgerRepository;
use App\Service\Exception\InsufficientStockException;
use App\Service\SalesOrderService;

/**
 * @group integration
 */
final class GoodsIssueIntegrationTest extends IntegrationTestCase
{
    private function approvedOrder(MySqlSalesOrderRepository $orders, SalesOrderService $service, int $qty): SalesOrder
    {
        $so = $service->create([
            'customer_id' => 1,
            'warehouse_id' => 1,
            'order_date' => date('Y-m-d'),
            'items' => [
                ['product_id' => 1, 'qty' => $qty],
            ],
        ], 2); // created by user 2 (not Admin, so approver id 1 differs)

        $service->submitForApproval($so->id, 2);
        $service->approve($so->id, 1, 'Admin');

        return $orders->findById($so->id);
    }

    public function testSecondGoodsIssueIsRejectedOnceStockIsExhausted(): void
    {
        $this->seedBaseline();
        $pdo = $this->pdo();
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, role) VALUES (2, 'Sales User', 'sales@test.local', 'x', 'Sales')");
        $pdo->exec('INSERT INTO product_stocks (product_id, warehouse_id, quantity) VALUES (1, 1, 10)');

        $orders = new MySqlSalesOrderRepository($pdo);
        $stocks = new MySqlProductStockRepository($pdo);
        $ledger = new MySqlStockLedgerRepository($pdo);
        $service = new SalesOrderService($orders, $stocks, $ledger, new PdoTransactionManager($pdo), new MySqlProductRepository($pdo));

        $so1 = $this->approvedOrder($orders, $service, 10);
        $result1 = $service->fulfill($so1->id, 1);
        $this->assertSame(SalesOrder::STATUS_FULFILLED, $result1->status);
        $this->assertSame(0, $stocks->find(1, 1)->quantity);

        $so2 = $this->approvedOrder($orders, $service, 10);

        $this->expectException(InsufficientStockException::class);
        try {
            $service->fulfill($so2->id, 1);
        } finally {
            // second order must remain Approved (rolled back), stock still 0
            $this->assertSame(SalesOrder::STATUS_APPROVED, $orders->findById($so2->id)->status);
            $this->assertSame(0, $stocks->find(1, 1)->quantity);
        }
    }
}
