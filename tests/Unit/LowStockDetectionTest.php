<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\Product;
use App\Repository\InMemoryProductRepository;
use App\Repository\InMemoryProductStockRepository;
use PHPUnit\Framework\TestCase;

/**
 * Reorder-point / low-stock detection: a product whose total stock across
 * warehouses is below its reorder_point must be reported as low stock;
 * one at or above its reorder_point must not.
 */
final class LowStockDetectionTest extends TestCase
{
    public function testProductBelowReorderPointIsFlaggedLowStock(): void
    {
        $products = new InMemoryProductRepository();
        $low = $products->save(new Product(null, 'LOW-1', 'Low Stock Item', 1, 'pcs', 100, 150, 10));

        $stocks = new InMemoryProductStockRepository($products);
        $stocks->setQuantity($low->id, 1, 4); // 4 < reorder point 10

        $list = $stocks->lowStockList();

        $this->assertCount(1, $list);
        $this->assertSame($low->id, $list[0]['product_id']);
        $this->assertSame(4, $list[0]['total']);
        $this->assertSame(10, $list[0]['reorder_point']);
        $this->assertSame(1, $stocks->countLowStock());
    }

    public function testProductAtOrAboveReorderPointIsNotFlagged(): void
    {
        $products = new InMemoryProductRepository();
        $ok = $products->save(new Product(null, 'OK-1', 'Healthy Stock Item', 1, 'pcs', 100, 150, 10));

        $stocks = new InMemoryProductStockRepository($products);
        $stocks->setQuantity($ok->id, 1, 10); // equal to reorder point -> not low

        $this->assertSame(0, $stocks->countLowStock());
        $this->assertSame([], $stocks->lowStockList());

        $stocks->setQuantity($ok->id, 1, 25); // comfortably above -> not low
        $this->assertSame(0, $stocks->countLowStock());
    }

    public function testTotalIsSummedAcrossWarehousesForReorderComparison(): void
    {
        $products = new InMemoryProductRepository();
        $product = $products->save(new Product(null, 'MULTI-1', 'Multi Warehouse Item', 1, 'pcs', 100, 150, 20));

        $stocks = new InMemoryProductStockRepository($products);
        $stocks->setQuantity($product->id, 1, 8);
        $stocks->setQuantity($product->id, 2, 5); // total 13 < reorder point 20

        $list = $stocks->lowStockList();

        $this->assertCount(1, $list);
        $this->assertSame(13, $list[0]['total']);
    }
}
