<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\Product;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderItem;
use App\Entity\User;
use App\Repository\InMemoryProductRepository;
use App\Repository\InMemoryProductStockRepository;
use App\Repository\InMemorySalesOrderRepository;
use App\Repository\InMemoryUserRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * The in-memory repositories are the test doubles behind the unit tests, so
 * they get their own contract checks to keep them faithful to the interfaces.
 */
final class InMemoryRepositoriesTest extends TestCase
{
    public function testUserRepositoryLooksUpSavesAndChecksEmailUniqueness(): void
    {
        $repo = new InMemoryUserRepository();
        $a = $repo->save(new User(null, 'Ann', 'ann@x.test', 'h', 'Admin'));
        $b = $repo->save(new User(null, 'Bob', 'bob@x.test', 'h', 'Sales'));
        $repo->save($a);

        $this->assertSame([1, 2], [$a->id, $b->id]);
        $this->assertCount(2, $repo->all());
        $this->assertSame($b, $repo->findById(2));
        $this->assertNull($repo->findById(99));
        $this->assertSame($a, $repo->findByEmail('ann@x.test'));
        $this->assertNull($repo->findByEmail('none@x.test'));
        $this->assertTrue($repo->emailExists('ann@x.test'));
        $this->assertFalse($repo->emailExists('ann@x.test', $a->id));
        $this->assertFalse($repo->emailExists('none@x.test'));
    }

    public function testProductRepositoryLookupsAndFilters(): void
    {
        $repo = new InMemoryProductRepository();
        $p = $repo->save(new Product(null, 'P-1', 'One', 1, 'pcs', 1, 2, 3));
        $repo->save(new Product(null, 'P-2', 'Two', 1, 'pcs', 1, 2, 3, null, false));

        $this->assertSame($p, $repo->findById($p->id));
        $this->assertNull($repo->findById(99));
        $this->assertSame($p, $repo->findBySku('P-1'));
        $this->assertNull($repo->findBySku('NOPE'));
        $this->assertTrue($repo->skuExists('P-1'));
        $this->assertFalse($repo->skuExists('P-1', $p->id));
        $this->assertCount(2, $repo->search());
        $this->assertSame(2, $repo->countSearch());
        $this->assertCount(1, $repo->all(true));
        $this->assertCount(2, $repo->all());

        $this->assertFalse($repo->isUsedInOrders($p->id));
        $repo->markUsedInOrders($p->id);
        $this->assertTrue($repo->isUsedInOrders($p->id));
    }

    public function testStockRepositoryTracksQuantitiesPerProductAndWarehouse(): void
    {
        $products = new InMemoryProductRepository();
        $p = $products->save(new Product(null, 'P-1', 'One', 1, 'pcs', 1, 2, 10));
        $stocks = new InMemoryProductStockRepository($products);

        $this->assertNull($stocks->find($p->id, 1));
        $stocks->setQuantity($p->id, 1, 4);
        $stocks->increment($p->id, 2, 3);
        $stocks->increment($p->id, 1, 1);
        $stocks->decrement($p->id, 2, 1);
        $stocks->decrement($p->id, 9, 2);

        $this->assertSame(5, $stocks->find($p->id, 1)->quantity);
        $this->assertCount(3, $stocks->findByProduct($p->id));
        $this->assertCount(1, $stocks->findByWarehouse(2));
        $this->assertSame(5, $stocks->totalForProduct($p->id));
        $this->assertSame(5, $stocks->lockForUpdate(new PDO('sqlite::memory:'), $p->id, 1));
        $this->assertSame(0, $stocks->lockForUpdate(new PDO('sqlite::memory:'), $p->id, 77));
        $this->assertSame(1, $stocks->countLowStock());
        $this->assertSame([], (new InMemoryProductStockRepository())->lowStockList());
    }

    public function testSalesOrderRepositoryStoresSearchesAndUpdatesOrders(): void
    {
        $repo = new InMemorySalesOrderRepository();
        $a = $repo->save(new SalesOrder(null, 'SO-1', 1, 1, 7, null, SalesOrder::STATUS_DRAFT, '2026-01-01'));
        $b = $repo->save(new SalesOrder(null, 'SO-2', 1, 1, 8, null, SalesOrder::STATUS_APPROVED, '2026-01-02'));
        $item = $repo->saveItem(new SalesOrderItem(null, $a->id, 1, 2, 10.0));
        $repo->saveItem($item);

        $this->assertSame($a, $repo->findById($a->id));
        $this->assertNull($repo->findById(99));
        $this->assertNull($repo->findWithItems(99));
        $this->assertCount(1, $repo->findWithItems($a->id)->items);
        $this->assertCount(1, $repo->itemsFor($a->id));
        $this->assertTrue($repo->soNumberExists('SO-2'));
        $this->assertFalse($repo->soNumberExists('SO-9'));
        $this->assertCount(2, $repo->search());
        $this->assertCount(1, $repo->search(['status' => SalesOrder::STATUS_APPROVED]));
        $this->assertSame(1, $repo->countSearch(['created_by' => 7]));

        $repo->updateStatus($a->id, SalesOrder::STATUS_APPROVED, 3);
        $repo->updateStatus($b->id, SalesOrder::STATUS_FULFILLED);
        $repo->updateStatus(99, SalesOrder::STATUS_FULFILLED);
        $this->assertSame(3, $a->approvedBy);
        $this->assertSame([SalesOrder::STATUS_APPROVED => 1, SalesOrder::STATUS_FULFILLED => 1], $repo->countsByStatus());
    }
}
