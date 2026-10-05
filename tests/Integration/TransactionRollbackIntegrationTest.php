<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Entity\SalesOrder;
use App\Repository\MySqlProductRepository;
use App\Repository\MySqlProductStockRepository;
use App\Repository\PdoTransactionManager;
use App\Repository\MySqlSalesOrderRepository;
use App\Repository\MySqlStockLedgerRepository;
use App\Service\SalesOrderService;
use PDOException;
use Throwable;

/**
 * @group integration
 *
 * Confirms that a failure partway through a multi-item fulfillment (goods
 * issue) transaction rolls the whole operation back — no partial stock
 * decrement survives and the order status is left unchanged — rather than
 * leaving the database in a half-applied state.
 */
final class TransactionRollbackIntegrationTest extends IntegrationTestCase
{
    public function testFailureMidTransactionRollsBackAllPriorWritesInTheSameFulfillment(): void
    {
        $this->seedBaseline();
        $pdo = $this->pdo();
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, role) VALUES (2, 'Sales User', 'sales@test.local', 'x', 'Sales')");
        $pdo->exec('INSERT INTO product_stocks (product_id, warehouse_id, quantity) VALUES (1, 1, 50)');

        $orders = new MySqlSalesOrderRepository($pdo);
        $stocks = new MySqlProductStockRepository($pdo);
        $ledger = new MySqlStockLedgerRepository($pdo);
        $service = new SalesOrderService($orders, $stocks, $ledger, new PdoTransactionManager($pdo), new MySqlProductRepository($pdo));

        $so = $service->create([
            'customer_id' => 1,
            'warehouse_id' => 1,
            'order_date' => date('Y-m-d'),
            'items' => [
                ['product_id' => 1, 'qty' => 5],
            ],
        ], 2);
        $service->submitForApproval($so->id, 2);
        $service->approve($so->id, 1, 'Admin');

        // Stock is decremented first, then the ledger row is written; a
        // non-existent performed_by user violates fk_ledger_performed_by
        // *after* the decrement has already happened in this transaction,
        // exercising the rollback path rather than the up-front stock check.
        $threw = false;
        try {
            $service->fulfill($so->id, 9999);
        } catch (Throwable $e) {
            $threw = true;
            $this->assertInstanceOf(PDOException::class, $e);
        }

        $this->assertTrue($threw, 'Expected the fulfillment to throw when the performing user does not exist.');

        // Rolled back: product 1's stock must be untouched and the order
        // must still be Approved, not Fulfilled.
        $this->assertSame(50, $stocks->find(1, 1)->quantity);
        $this->assertSame(SalesOrder::STATUS_APPROVED, $orders->findById($so->id)->status);
        $this->assertCount(0, $ledger->search(['product_id' => 1]));
    }
}
