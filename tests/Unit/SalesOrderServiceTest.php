<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Repository\InMemoryProductStockRepository;
use App\Repository\InMemorySalesOrderRepository;
use App\Repository\StockLedgerRepositoryInterface;
use App\Service\Exception\AuthorizationException;
use App\Service\Exception\InsufficientStockException;
use App\Service\Exception\InvalidStatusTransitionException;
use App\Service\SalesOrderService;
use PDO;
use PHPUnit\Framework\TestCase;

final class SalesOrderServiceTest extends TestCase
{
    private function pdo(): PDO
    {
        // A real (in-memory sqlite) PDO so beginTransaction/commit/rollBack
        // work; the fakes never issue real SQL against it.
        return new PDO('sqlite::memory:');
    }

    private function makeService(InMemorySalesOrderRepository $orders, InMemoryProductStockRepository $stocks): SalesOrderService
    {
        return new SalesOrderService($orders, $stocks, new FakeStockLedgerRepository(), $this->pdo());
    }

    private function seedApprovedOrder(InMemorySalesOrderRepository $orders, int $qty = 10): SalesOrder
    {
        $so = $orders->save(new SalesOrder(null, 'SO-TEST-1', 1, 1, 2, 1, SalesOrder::STATUS_APPROVED, '2026-01-01'));
        $orders->saveItem(new SalesOrderItem(null, $so->id, 100, $qty, 5000));

        return $so;
    }

    public function testFulfillThrowsInsufficientStockAndRollsBackWhenStockTooLow(): void
    {
        $orders = new InMemorySalesOrderRepository();
        $stocks = new InMemoryProductStockRepository();
        $so = $this->seedApprovedOrder($orders, 10);
        $stocks->setQuantity(100, 1, 3); // only 3 available, need 10

        $service = $this->makeService($orders, $stocks);

        $this->expectException(InsufficientStockException::class);
        try {
            $service->fulfill($so->id, 4);
        } finally {
            // status must remain unchanged (rolled back)
            $this->assertSame(SalesOrder::STATUS_APPROVED, $orders->findById($so->id)->status);
            $this->assertSame(3, $stocks->find(100, 1)->quantity);
        }
    }

    public function testFulfillSucceedsAndDecrementsStockWhenSufficient(): void
    {
        $orders = new InMemorySalesOrderRepository();
        $stocks = new InMemoryProductStockRepository();
        $so = $this->seedApprovedOrder($orders, 10);
        $stocks->setQuantity(100, 1, 50);

        $service = $this->makeService($orders, $stocks);
        $result = $service->fulfill($so->id, 4);

        $this->assertSame(SalesOrder::STATUS_FULFILLED, $result->status);
        $this->assertSame(40, $stocks->find(100, 1)->quantity);
    }

    public function testInvalidStatusTransitionIsRejected(): void
    {
        $orders = new InMemorySalesOrderRepository();
        $stocks = new InMemoryProductStockRepository();
        $so = $orders->save(new SalesOrder(null, 'SO-TEST-2', 1, 1, 2, null, SalesOrder::STATUS_DRAFT, '2026-01-01'));

        $service = $this->makeService($orders, $stocks);

        $this->expectException(InvalidStatusTransitionException::class);
        $service->approve($so->id, 1, 'Admin'); // Draft -> Approved is not allowed directly
    }

    public function testSalesRoleCannotApproveEvenWhenCallingServiceDirectly(): void
    {
        $orders = new InMemorySalesOrderRepository();
        $stocks = new InMemoryProductStockRepository();
        $so = $orders->save(new SalesOrder(null, 'SO-TEST-3', 1, 1, 2, null, SalesOrder::STATUS_PENDING_APPROVAL, '2026-01-01'));

        $service = $this->makeService($orders, $stocks);

        $this->expectException(AuthorizationException::class);
        $service->approve($so->id, 3, 'Sales');
    }

    public function testCreatorCannotApproveOwnOrder(): void
    {
        $orders = new InMemorySalesOrderRepository();
        $stocks = new InMemoryProductStockRepository();
        $so = $orders->save(new SalesOrder(null, 'SO-TEST-4', 1, 1, 2, null, SalesOrder::STATUS_PENDING_APPROVAL, '2026-01-01'));

        $service = $this->makeService($orders, $stocks);

        $this->expectException(AuthorizationException::class);
        $service->approve($so->id, 2, 'Admin'); // same id as createdBy
    }

    public function testWarehouseStaffRoleCannotApprove(): void
    {
        $orders = new InMemorySalesOrderRepository();
        $stocks = new InMemoryProductStockRepository();
        $so = $orders->save(new SalesOrder(null, 'SO-TEST-6', 1, 1, 2, null, SalesOrder::STATUS_PENDING_APPROVAL, '2026-01-01'));

        $service = $this->makeService($orders, $stocks);

        $this->expectException(AuthorizationException::class);
        $service->approve($so->id, 3, 'WarehouseStaff');
    }

    public function testWarehouseStaffRoleCannotReject(): void
    {
        $orders = new InMemorySalesOrderRepository();
        $stocks = new InMemoryProductStockRepository();
        $so = $orders->save(new SalesOrder(null, 'SO-TEST-7', 1, 1, 2, null, SalesOrder::STATUS_PENDING_APPROVAL, '2026-01-01'));

        $service = $this->makeService($orders, $stocks);

        $this->expectException(AuthorizationException::class);
        $service->reject($so->id, 3, 'WarehouseStaff');
    }

    public function testFullValidLifecycleDraftToFulfilled(): void
    {
        $orders = new InMemorySalesOrderRepository();
        $stocks = new InMemoryProductStockRepository();
        $stocks->setQuantity(100, 1, 50);

        $so = $orders->save(new SalesOrder(null, 'SO-TEST-8', 1, 1, 2, null, SalesOrder::STATUS_DRAFT, '2026-01-01'));
        $orders->saveItem(new SalesOrderItem(null, $so->id, 100, 5, 5000));

        $service = $this->makeService($orders, $stocks);

        $service->submitForApproval($so->id, 2);
        $this->assertSame(SalesOrder::STATUS_PENDING_APPROVAL, $orders->findById($so->id)->status);

        $service->approve($so->id, 1, 'Admin');
        $this->assertSame(SalesOrder::STATUS_APPROVED, $orders->findById($so->id)->status);

        $result = $service->fulfill($so->id, 1);
        $this->assertSame(SalesOrder::STATUS_FULFILLED, $result->status);
        $this->assertSame(45, $stocks->find(100, 1)->quantity);
    }

    public function testFulfilledCannotTransitionBackToDraft(): void
    {
        $orders = new InMemorySalesOrderRepository();
        $stocks = new InMemoryProductStockRepository();
        $so = $orders->save(new SalesOrder(null, 'SO-TEST-9', 1, 1, 2, 1, SalesOrder::STATUS_FULFILLED, '2026-01-01'));

        $service = $this->makeService($orders, $stocks);

        $this->expectException(InvalidStatusTransitionException::class);
        $service->reject($so->id, 1, 'Admin'); // reject moves to Draft; must be rejected from Fulfilled
    }

    public function testApprovedCannotSkipStraightToFulfilledViaTransitionAssertionOnly(): void
    {
        // fulfill() is the only path Approved->Fulfilled may take, and it
        // requires sufficient stock; confirms there is no shortcut that
        // marks Fulfilled without going through fulfill()'s stock check.
        $orders = new InMemorySalesOrderRepository();
        $stocks = new InMemoryProductStockRepository();
        $so = $this->seedApprovedOrder($orders, 10);
        $stocks->setQuantity(100, 1, 0);

        $service = $this->makeService($orders, $stocks);

        $this->expectException(InsufficientStockException::class);
        $service->fulfill($so->id, 1);
    }

    public function testOnlyOwnerCanSubmitForApproval(): void
    {
        $orders = new InMemorySalesOrderRepository();
        $stocks = new InMemoryProductStockRepository();
        $so = $orders->save(new SalesOrder(null, 'SO-TEST-5', 1, 1, 2, null, SalesOrder::STATUS_DRAFT, '2026-01-01'));

        $service = $this->makeService($orders, $stocks);

        $this->expectException(AuthorizationException::class);
        $service->submitForApproval($so->id, 999);
    }
}

/**
 * Minimal stock ledger fake sufficient for these unit tests.
 */
final class FakeStockLedgerRepository implements StockLedgerRepositoryInterface
{
    public function record(\App\Entity\StockLedger $entry, ?PDO $pdo = null): \App\Entity\StockLedger
    {
        $entry->id = random_int(1, 1000000);

        return $entry;
    }

    public function search(array $filters = []): array
    {
        return [];
    }
}
