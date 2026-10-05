<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\Product;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Repository\InMemoryProductRepository;
use App\Repository\InMemoryTransactionManager;
use App\Repository\ProductStockRepositoryInterface;
use App\Repository\SalesOrderRepositoryInterface;
use App\Repository\StockLedgerRepositoryInterface;
use App\Service\Exception\ValidationException;
use App\Service\SalesOrderService;
use PHPUnit\Framework\TestCase;

final class SalesOrderCreationTest extends TestCase
{
    /** @var list<SalesOrder> */
    private array $savedOrders = [];
    /** @var list<SalesOrderItem> */
    private array $savedItems = [];
    private InMemoryTransactionManager $transactions;

    private function service(array $referenceErrors = []): SalesOrderService
    {
        $orders = $this->createMock(SalesOrderRepositoryInterface::class);
        $orders->method('invalidReferences')->willReturn($referenceErrors);
        $orders->method('save')->willReturnCallback(function (SalesOrder $so): SalesOrder {
            $so->id ??= 12;
            $this->savedOrders[] = clone $so;

            return $so;
        });
        $orders->method('saveItem')->willReturnCallback(function (SalesOrderItem $item): SalesOrderItem {
            $this->savedItems[] = $item;

            return $item;
        });

        $products = new InMemoryProductRepository();
        $products->save(new Product(null, 'SKU-1', 'Printer', 1, 'unit', 150.0, 200.0, 5));
        $this->transactions = new InMemoryTransactionManager();

        return new SalesOrderService(
            $orders,
            $this->createMock(ProductStockRepositoryInterface::class),
            $this->createMock(StockLedgerRepositoryInterface::class),
            $this->transactions,
            $products,
        );
    }

    private function input(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => 1,
            'warehouse_id' => 1,
            'order_date' => '2026-10-05',
            'items' => [['product_id' => 1, 'qty' => 2]],
        ], $overrides);
    }

    public function testNumberIsGeneratedFromOrderYearAndId(): void
    {
        $so = $this->service()->create($this->input(['so_number' => 'IGNORED']), 4);

        $this->assertSame('SO-2026-0012', $so->soNumber);
        $this->assertSame(SalesOrder::STATUS_DRAFT, $so->status);
        $this->assertSame(4, $so->createdBy);
        $this->assertStringStartsWith('PENDING-', $this->savedOrders[0]->soNumber, 'first saved with a unique placeholder');
        $this->assertSame('SO-2026-0012', $this->savedOrders[1]->soNumber, 'then updated with the final number');
    }

    public function testItemPriceComesFromTheProductNotFromTheRequest(): void
    {
        $this->service()->create($this->input(['items' => [['product_id' => 1, 'qty' => 2, 'selling_price' => 1]]]), 4);

        $this->assertCount(1, $this->savedItems);
        $this->assertSame(200.0, $this->savedItems[0]->sellingPrice);
        $this->assertSame(2, $this->savedItems[0]->qty);
    }

    public function testCreationRunsInsideOneTransaction(): void
    {
        $this->service()->create($this->input(), 4);

        $this->assertSame(1, $this->transactions->commits);
        $this->assertSame(0, $this->transactions->rollbacks);
    }

    public function testMissingFieldsAndInvalidDatesAreRejectedWithoutSaving(): void
    {
        $service = $this->service();

        foreach ([['order_date' => ''], ['order_date' => '31-31-2026']] as $overrides) {
            try {
                $service->create($this->input($overrides + ['customer_id' => '', 'warehouse_id' => '', 'items' => []]), 4);
                $this->fail('Expected ValidationException');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('customer_id', $e->errors());
                $this->assertArrayHasKey('warehouse_id', $e->errors());
                $this->assertArrayHasKey('order_date', $e->errors());
                $this->assertArrayHasKey('items', $e->errors());
            }
        }
        $this->assertSame([], $this->savedOrders);
    }

    public function testUnknownReferencesAreRejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->service(['customer_id' => 'Customer not found or inactive.'])->create($this->input(), 4);
    }
}
