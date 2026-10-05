<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\SalesOrder;
use App\Repository\InMemoryProductStockRepository;
use App\Repository\InMemorySalesOrderRepository;
use App\Repository\StockLedgerRepositoryInterface;
use App\Service\DateRules;
use App\Service\Exception\AuthorizationException;
use App\Service\OrderItemValidator;
use App\Service\SalesOrderService;
use App\Repository\InMemoryTransactionManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrderRulesTest extends TestCase
{
    #[DataProvider('dates')]
    public function testDateRulesAcceptOnlyRealYmdDates(string $value, bool $valid): void
    {
        $this->assertSame($valid, DateRules::isValidYmd($value));
    }

    /**
     * @return array<string,array{0:string,1:bool}>
     */
    public static function dates(): array
    {
        return [
            'valid' => ['2026-10-07', true], 'leap day' => ['2028-02-29', true],
            'impossible day' => ['2026-02-31', false], 'wrong order' => ['07-10-2026', false],
            'text' => ['tomorrow', false], 'empty' => ['', false], 'trailing junk' => ['2026-10-07 10:00', false],
        ];
    }

    /**
     * @return array<string,array{0:mixed,1:array<string,string>}>
     */
    public static function itemSets(): array
    {
        $ok = ['product_id' => '1', 'qty' => '2', 'selling_price' => '10.5'];

        return [
            'valid' => [[$ok], []],
            'valid without price' => [[['product_id' => 1, 'qty' => 1]], []],
            'no items' => [[], ['items' => 'At least one item is required.']],
            'not an array' => ['x', ['items' => 'At least one item is required.']],
            'item is not an array' => [['x'], ['items.0' => 'Each item requires a product and a positive quantity.']],
            'missing product' => [[['qty' => 1]], ['items.0' => 'Each item requires a product and a positive quantity.']],
            'zero quantity' => [[array_merge($ok, ['qty' => '0'])], ['items.0' => 'Each item requires a product and a positive quantity.']],
            'fractional quantity' => [[array_merge($ok, ['qty' => '1.5'])], ['items.0' => 'Each item requires a product and a positive quantity.']],
            'huge quantity' => [[array_merge($ok, ['qty' => (string) (OrderItemValidator::MAX_QUANTITY + 1)])], ['items.0' => 'Each item requires a product and a positive quantity.']],
            'negative price' => [[array_merge($ok, ['selling_price' => '-1'])], ['items.0' => 'Item price must be a non-negative number.']],
            'text price' => [[array_merge($ok, ['selling_price' => 'free'])], ['items.0' => 'Item price must be a non-negative number.']],
            'huge price' => [[array_merge($ok, ['selling_price' => '1e20'])], ['items.0' => 'Item price must be a non-negative number.']],
            'second item bad' => [[$ok, ['product_id' => '', 'qty' => '1']], ['items.1' => 'Each item requires a product and a positive quantity.']],
        ];
    }

    #[DataProvider('itemSets')]
    public function testItemValidatorRules(mixed $items, array $expected): void
    {
        $this->assertSame($expected, OrderItemValidator::validate($items, 'qty', 'selling_price'));
    }

    public function testProductIdsAreCastToIntegersKeepingKeys(): void
    {
        $this->assertSame([0 => 4, 3 => 9], OrderItemValidator::productIds([0 => ['product_id' => '4'], 3 => ['product_id' => 9]]));
    }

    private function service(InMemorySalesOrderRepository $orders): SalesOrderService
    {
        return new SalesOrderService(
            $orders,
            new InMemoryProductStockRepository(),
            $this->createMock(StockLedgerRepositoryInterface::class),
            new InMemoryTransactionManager(),
            new \App\Repository\InMemoryProductRepository(),
        );
    }

    public function testOnlyTheOwnerOrStaffCanCancelAnOrder(): void
    {
        $orders = new InMemorySalesOrderRepository();
        $so = $orders->save(new SalesOrder(null, 'SO-1', 1, 1, 7, null, SalesOrder::STATUS_DRAFT, '2026-01-01'));
        $service = $this->service($orders);

        try {
            $service->cancel($so->id, 8, 'Sales');
            $this->fail('A different Sales user must not cancel the order.');
        } catch (AuthorizationException $e) {
            $this->assertSame(SalesOrder::STATUS_DRAFT, $so->status);
        }

        $this->assertSame(SalesOrder::STATUS_CANCELLED, $service->cancel($so->id, 7, 'Sales')->status);

        $other = $orders->save(new SalesOrder(null, 'SO-2', 1, 1, 7, null, SalesOrder::STATUS_DRAFT, '2026-01-01'));
        $this->assertSame(SalesOrder::STATUS_CANCELLED, $service->cancel($other->id, 1, 'Admin')->status);
    }
}
