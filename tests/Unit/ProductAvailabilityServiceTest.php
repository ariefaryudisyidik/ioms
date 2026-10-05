<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\Product;
use App\Entity\Warehouse;
use App\Repository\InMemoryProductRepository;
use App\Repository\InMemoryProductStockRepository;
use App\Repository\InMemoryWarehouseRepository;
use App\Service\ProductAvailabilityService;
use PHPUnit\Framework\TestCase;

final class ProductAvailabilityServiceTest extends TestCase
{
    private InMemoryProductRepository $products;
    private InMemoryProductStockRepository $stocks;
    private InMemoryWarehouseRepository $warehouses;
    private ProductAvailabilityService $service;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepository();
        $this->stocks = new InMemoryProductStockRepository($this->products);
        $this->warehouses = new InMemoryWarehouseRepository();
        $this->service = new ProductAvailabilityService($this->products, $this->stocks, $this->warehouses);
    }

    public function testUnknownSkuReturnsNull(): void
    {
        $this->assertNull($this->service->forSku('NOPE'));
    }

    public function testSumsStockAcrossWarehouses(): void
    {
        $product = $this->products->save(new Product(null, 'SKU-1', 'Printer', 1, 'unit', 100, 150, 5));
        $jakarta = $this->warehouses->save(new Warehouse(null, 'Jakarta', 'JKT'));
        $surabaya = $this->warehouses->save(new Warehouse(null, 'Surabaya', 'SBY'));
        $this->stocks->setQuantity($product->id, $jakarta->id, 4);
        $this->stocks->setQuantity($product->id, $surabaya->id, 0);

        $result = $this->service->forSku('SKU-1');

        $this->assertSame('SKU-1', $result['sku']);
        $this->assertSame('Printer', $result['name']);
        $this->assertSame(4, $result['total']);
        $this->assertSame(
            [['warehouse' => 'Jakarta', 'quantity' => 4], ['warehouse' => 'Surabaya', 'quantity' => 0]],
            $result['warehouses']
        );
    }

    public function testProductWithoutStockRowsHasZeroTotal(): void
    {
        $this->products->save(new Product(null, 'SKU-2', 'Cable', 1, 'pcs', 10, 15, 5));

        $result = $this->service->forSku('SKU-2');

        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['warehouses']);
    }

    public function testUnknownWarehouseFallsBackToIdLabel(): void
    {
        $product = $this->products->save(new Product(null, 'SKU-3', 'Mouse', 1, 'pcs', 10, 15, 5));
        $this->stocks->setQuantity($product->id, 99, 7);

        $result = $this->service->forSku('SKU-3');

        $this->assertSame([['warehouse' => '#99', 'quantity' => 7]], $result['warehouses']);
    }

    public function testWarehouseFakeCanListOnlyActiveWarehouses(): void
    {
        $this->warehouses->save(new Warehouse(null, 'Active', 'A'));
        $this->warehouses->save(new Warehouse(null, 'Closed', 'C', false));

        $this->assertCount(2, $this->warehouses->all());
        $this->assertCount(1, $this->warehouses->all(true));
    }
}
