<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Entity\PurchaseOrder;
use App\Repository\MySqlProductStockRepository;
use App\Repository\PdoTransactionManager;
use App\Repository\MySqlPurchaseOrderRepository;
use App\Repository\MySqlStockLedgerRepository;
use App\Service\PurchaseOrderService;

/**
 * @group integration
 */
final class GoodsReceiptIntegrationTest extends IntegrationTestCase
{
    public function testReceivingGoodsIncrementsStockAndWritesLedgerEntry(): void
    {
        $this->seedBaseline();
        $pdo = $this->pdo();

        $purchaseOrders = new MySqlPurchaseOrderRepository($pdo);
        $stocks = new MySqlProductStockRepository($pdo);
        $ledger = new MySqlStockLedgerRepository($pdo);
        $service = new PurchaseOrderService($purchaseOrders, $stocks, $ledger, new PdoTransactionManager($pdo));

        $po = $service->create([
            'po_number' => 'PO-INT-1',
            'supplier_id' => 1,
            'warehouse_id' => 1,
            'order_date' => date('Y-m-d'),
            'items' => [
                ['product_id' => 1, 'qty_ordered' => 20, 'purchase_price' => 100],
            ],
        ], 1);
        $service->transitionTo($po->id, PurchaseOrder::STATUS_ORDERED);

        $items = $purchaseOrders->itemsFor($po->id);
        $this->assertCount(1, $items);

        $result = $service->receiveGoods($po->id, [
            ['item_id' => $items[0]->id, 'qty' => 20],
        ], 1);

        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $result->status);

        $stock = $stocks->find(1, 1);
        $this->assertNotNull($stock);
        $this->assertSame(20, $stock->quantity);

        $ledgerRows = $ledger->search(['product_id' => 1]);
        $this->assertCount(1, $ledgerRows);
        $this->assertSame('Receipt', $ledgerRows[0]->movementType);
        $this->assertSame(20, $ledgerRows[0]->quantity);
        $this->assertSame('purchase_order', $ledgerRows[0]->referenceType);
        $this->assertSame($po->id, $ledgerRows[0]->referenceId);
    }

    public function testPartialThenFullReceiptMovesStatusThroughPartiallyReceivedToReceived(): void
    {
        $this->seedBaseline();
        $pdo = $this->pdo();

        $purchaseOrders = new MySqlPurchaseOrderRepository($pdo);
        $stocks = new MySqlProductStockRepository($pdo);
        $ledger = new MySqlStockLedgerRepository($pdo);
        $service = new PurchaseOrderService($purchaseOrders, $stocks, $ledger, new PdoTransactionManager($pdo));

        $po = $service->create([
            'po_number' => 'PO-INT-2',
            'supplier_id' => 1,
            'warehouse_id' => 1,
            'order_date' => date('Y-m-d'),
            'items' => [
                ['product_id' => 1, 'qty_ordered' => 10, 'purchase_price' => 100],
            ],
        ], 1);
        $service->transitionTo($po->id, PurchaseOrder::STATUS_ORDERED);
        $itemId = $purchaseOrders->itemsFor($po->id)[0]->id;

        $partial = $service->receiveGoods($po->id, [
            ['item_id' => $itemId, 'qty' => 4],
        ], 1);
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $partial->status);
        $this->assertSame(4, $stocks->find(1, 1)->quantity);

        $full = $service->receiveGoods($po->id, [
            ['item_id' => $itemId, 'qty' => 6],
        ], 1);
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $full->status);
        $this->assertSame(10, $stocks->find(1, 1)->quantity);

        $ledgerRows = $ledger->search(['product_id' => 1]);
        $this->assertCount(2, $ledgerRows);
    }
}
