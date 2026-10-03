<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Repository\MySqlProductRepository;
use App\Repository\MySqlProductStockRepository;
use App\Repository\MySqlPurchaseOrderRepository;
use App\Repository\MySqlSalesOrderRepository;
use App\Repository\MySqlStockLedgerRepository;
use App\Service\PurchaseOrderService;
use PDOException;
use Throwable;

/**
 * @group integration
 *
 * Repository paths that the HTTP flows do not reach (updates of existing
 * rows, stock lookups, filters) plus the goods-receipt rollback.
 */
final class RepositoryIntegrationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBaseline();
    }

    public function testSalesOrderAndItemAreInsertedThenUpdatedInPlace(): void
    {
        $repo = new MySqlSalesOrderRepository($this->pdo());
        $so = $repo->save(new SalesOrder(null, 'SO-R-1', 1, 1, 1, null, SalesOrder::STATUS_DRAFT, '2026-01-01'));

        $so->soNumber = 'SO-R-2';
        $so->status = SalesOrder::STATUS_APPROVED;
        $so->approvedBy = 1;
        $repo->save($so);

        $item = $repo->saveItem(new SalesOrderItem(null, $so->id, 1, 2, 100.0));
        $item->qty = 5;
        $repo->saveItem($item);

        $loaded = $repo->findWithItems($so->id);
        $this->assertSame('SO-R-2', $loaded->soNumber);
        $this->assertSame(SalesOrder::STATUS_APPROVED, $loaded->status);
        $this->assertCount(1, $loaded->items);
        $this->assertSame(5, $loaded->items[0]->qty);
    }

    public function testPurchaseOrderAndItemAreInsertedThenUpdatedInPlace(): void
    {
        $repo = new MySqlPurchaseOrderRepository($this->pdo());
        $po = $repo->save(new PurchaseOrder(null, 'PO-R-1', 1, 1, PurchaseOrder::STATUS_DRAFT, '2026-01-01', 1));

        $po->poNumber = 'PO-R-2';
        $po->status = PurchaseOrder::STATUS_ORDERED;
        $repo->save($po);

        $item = $repo->saveItem(new PurchaseOrderItem(null, $po->id, 1, 10, 0, 100.0));
        $item->qtyOrdered = 12;
        $repo->saveItem($item);

        $loaded = $repo->findWithItems($po->id);
        $this->assertSame('PO-R-2', $loaded->poNumber);
        $this->assertSame(PurchaseOrder::STATUS_ORDERED, $loaded->status);
        $this->assertCount(1, $loaded->items);
        $this->assertSame(12, $loaded->items[0]->qtyOrdered);
    }

    public function testStockLookupsByWarehouseAndTotal(): void
    {
        $this->pdo()->exec("INSERT INTO warehouses (id, name) VALUES (2, 'Second')");
        $stocks = new MySqlProductStockRepository($this->pdo());
        $stocks->increment(1, 1, 7);
        $stocks->increment(1, 2, 3);

        $this->assertCount(1, $stocks->findByWarehouse(2));
        $this->assertSame(3, $stocks->findByWarehouse(2)[0]->quantity);
        $this->assertSame(10, $stocks->totalForProduct(1));
        $this->assertSame(0, $stocks->totalForProduct(99));
    }

    public function testProductSearchSupportsActiveFlagAndStockStatusFilters(): void
    {
        $this->pdo()->exec("INSERT INTO products (id, sku, name, category_id, unit, purchase_price, selling_price, reorder_point, is_active)
                            VALUES (2, 'SKU-2', 'Inactive Item', 1, 'pcs', 1, 2, 5, 0)");
        (new MySqlProductStockRepository($this->pdo()))->increment(1, 1, 50);
        $products = new MySqlProductRepository($this->pdo());

        $this->assertCount(1, $products->search(['is_active' => 0]));
        $this->assertSame(1, $products->countSearch(['is_active' => 1]));
        $this->assertCount(1, $products->search(['status' => 'normal']));
        $this->assertCount(1, $products->search(['status' => 'low']));
    }

    public function testFailureWhileReceivingGoodsRollsBackItemQuantitiesAndStatus(): void
    {
        $pdo = $this->pdo();
        $orders = new MySqlPurchaseOrderRepository($pdo);
        $stocks = new MySqlProductStockRepository($pdo);
        $service = new PurchaseOrderService($orders, $stocks, new MySqlStockLedgerRepository($pdo), $pdo);

        $po = $service->create([
            'po_number' => 'PO-R-ROLLBACK',
            'supplier_id' => 1,
            'warehouse_id' => 1,
            'order_date' => date('Y-m-d'),
            'items' => [['product_id' => 1, 'qty_ordered' => 10, 'purchase_price' => 100]],
        ], 1);
        $service->transitionTo($po->id, PurchaseOrder::STATUS_ORDERED);
        $itemId = $orders->findWithItems($po->id)->items[0]->id;

        // A non-existent performer breaks the ledger FK after the item row was updated.
        $threw = null;
        try {
            $service->receiveGoods($po->id, [['item_id' => $itemId, 'qty' => 4]], 9999);
        } catch (Throwable $e) {
            $threw = $e;
        }

        $this->assertInstanceOf(PDOException::class, $threw);
        $loaded = $orders->findWithItems($po->id);
        $this->assertSame(0, $loaded->items[0]->qtyReceived);
        $this->assertSame(PurchaseOrder::STATUS_ORDERED, $loaded->status);
        $this->assertSame(0, $stocks->totalForProduct(1));
    }
}
