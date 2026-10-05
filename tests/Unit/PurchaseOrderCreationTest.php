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
use PHPUnit\Framework\TestCase;

final class PurchaseOrderCreationTest extends TestCase
{
    /** @var list<PurchaseOrder> */
    private array $savedOrders = [];
    /** @var list<PurchaseOrderItem> */
    private array $savedItems = [];
    private InMemoryTransactionManager $transactions;
    private InMemoryProductRepository $products;

    private function service(array $referenceErrors = []): PurchaseOrderService
    {
        $orders = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $orders->method('invalidReferences')->willReturn($referenceErrors);
        $orders->method('save')->willReturnCallback(function (PurchaseOrder $po): PurchaseOrder {
            $po->id ??= 7;
            $this->savedOrders[] = clone $po;

            return $po;
        });
        $orders->method('saveItem')->willReturnCallback(function (PurchaseOrderItem $item): PurchaseOrderItem {
            $this->savedItems[] = $item;

            return $item;
        });

        $this->transactions = new InMemoryTransactionManager();
        $this->products = new InMemoryProductRepository();
        $this->products->save(new Product(null, 'SKU-1', 'Printer', 1, 'unit', 150.0, 200.0, 5));

        return new PurchaseOrderService(
            $orders,
            $this->createMock(ProductStockRepositoryInterface::class),
            $this->createMock(StockLedgerRepositoryInterface::class),
            $this->transactions,
            $this->products,
        );
    }

    private function input(array $overrides = []): array
    {
        return array_merge([
            'supplier_id' => 1,
            'warehouse_id' => 1,
            'order_date' => date('Y-m-d'),
            'items' => [['product_id' => 1, 'qty_ordered' => 3]],
        ], $overrides);
    }

    public function testNumberIsGeneratedFromOrderYearAndId(): void
    {
        $po = $this->service()->create($this->input(['order_date' => date('Y-m-d'), 'po_number' => 'IGNORED']), 9);

        $this->assertSame(sprintf('PO-%s-0007', date('Y')), $po->poNumber);
        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $po->status);
        $this->assertSame(9, $po->createdBy);
        $this->assertStringStartsWith('PENDING-', $this->savedOrders[0]->poNumber, 'first saved with a unique placeholder');
        $this->assertSame($po->poNumber, $this->savedOrders[1]->poNumber, 'then updated with the final number');
    }

    public function testItemPriceComesFromTheProductNotFromTheRequest(): void
    {
        $this->service()->create($this->input(['items' => [['product_id' => 1, 'qty_ordered' => 3, 'purchase_price' => 1]]]), 1);

        $this->assertCount(1, $this->savedItems);
        $this->assertSame(150.0, $this->savedItems[0]->purchasePrice);
        $this->assertSame(3, $this->savedItems[0]->qtyOrdered);
        $this->assertSame(0, $this->savedItems[0]->qtyReceived);
    }

    public function testCreationRunsInsideOneTransaction(): void
    {
        $this->service()->create($this->input(), 1);

        $this->assertSame(1, $this->transactions->commits);
        $this->assertSame(0, $this->transactions->rollbacks);
    }

    public function testMissingSupplierWarehouseAndItemsAreRejectedWithoutSaving(): void
    {
        $service = $this->service();

        try {
            $service->create(['order_date' => date('Y-m-d')], 1);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('supplier_id', $e->errors());
            $this->assertArrayHasKey('warehouse_id', $e->errors());
            $this->assertArrayHasKey('items', $e->errors());
        }
        $this->assertSame([], $this->savedOrders);
    }

    public function testUnknownReferencesAreRejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->service(['supplier_id' => 'Supplier not found or inactive.'])->create($this->input(), 1);
    }
}
