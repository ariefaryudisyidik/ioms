<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\Product;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Repository\InMemoryProductRepository;
use App\Repository\InMemoryTransactionManager;
use App\Repository\ProductStockRepositoryInterface;
use App\Repository\PurchaseOrderRepositoryInterface;
use App\Repository\StockLedgerRepositoryInterface;
use App\Service\Exception\ValidationException;
use App\Service\PurchaseOrderService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PurchaseOrderReceiptValidationTest extends TestCase
{
    private MockObject&PurchaseOrderRepositoryInterface $orders;
    private MockObject&StockLedgerRepositoryInterface $ledger;
    private MockObject&ProductStockRepositoryInterface $stocks;
    private PurchaseOrderService $service;

    protected function setUp(): void
    {
        $products = new InMemoryProductRepository();
        $products->save(new Product(null, 'SKU-1', 'Amplop Coklat', 1, 'box', 15000.0, 22000.0, 5));

        $po = new PurchaseOrder(7, 'PO-2026-0007', 1, 1, PurchaseOrder::STATUS_ORDERED, '2026-10-05', 1);
        $po->items = [new PurchaseOrderItem(11, 7, 1, 1, 0, 15000.0)];

        $this->orders = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $this->orders->method('findWithItems')->willReturn($po);
        $this->ledger = $this->createMock(StockLedgerRepositoryInterface::class);
        $this->stocks = $this->createMock(ProductStockRepositoryInterface::class);

        $this->service = new PurchaseOrderService(
            $this->orders,
            $this->stocks,
            $this->ledger,
            new InMemoryTransactionManager(),
            $products,
        );
    }

    public function testReceivingMoreThanRemainingIsRejectedAndNothingIsSaved(): void
    {
        $this->orders->expects($this->never())->method('updateItemReceived');
        $this->orders->expects($this->never())->method('updateStatus');
        $this->ledger->expects($this->never())->method('record');
        $this->stocks->expects($this->never())->method('increment');

        try {
            $this->service->receiveGoods(7, [['item_id' => 11, 'qty' => 3]], 1);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(['items.11' => 'Amplop Coklat: cannot receive 3, only 1 remaining.'], $e->errors());
        }
    }

    public function testReceivingExactlyTheRemainingQuantityCompletesTheOrder(): void
    {
        $this->orders->expects($this->once())->method('updateItemReceived')->with(11, 1);
        $this->orders->expects($this->once())->method('updateStatus')->with(7, PurchaseOrder::STATUS_RECEIVED);
        $this->ledger->expects($this->once())->method('record')->willReturnArgument(0);
        $this->stocks->expects($this->once())->method('increment')->with(1, 1, 1);

        $po = $this->service->receiveGoods(7, [['item_id' => 11, 'qty' => 1]], 1);

        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $po->status);
    }

    public function testReceiptWithNothingToReceiveIsRejected(): void
    {
        $this->orders->expects($this->never())->method('updateItemReceived');
        $this->orders->expects($this->never())->method('updateStatus');
        $this->ledger->expects($this->never())->method('record');

        // Zero quantities and unknown items do not count as receiving anything.
        foreach ([[['item_id' => 11, 'qty' => 0]], [['item_id' => 999, 'qty' => 5]], []] as $items) {
            try {
                $this->service->receiveGoods(7, $items, 1);
                $this->fail('Expected ValidationException');
            } catch (ValidationException $e) {
                $this->assertSame(['items' => 'Enter a quantity to receive for at least one item.'], $e->errors());
            }
        }
    }

    public function testZeroLinesAreIgnoredWhenAnotherLineReceivesSomething(): void
    {
        $this->orders->expects($this->once())->method('updateItemReceived')->with(11, 1);
        $this->ledger->expects($this->once())->method('record')->willReturnArgument(0);

        $po = $this->service->receiveGoods(7, [['item_id' => 11, 'qty' => 1], ['item_id' => 999, 'qty' => 0]], 1);

        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $po->status);
    }
}
