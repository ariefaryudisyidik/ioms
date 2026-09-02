<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entity\Category;
use App\Entity\Product;
use App\Repository\CategoryRepositoryInterface;
use App\Repository\InMemoryProductRepository;
use App\Service\Exception\ValidationException;
use App\Service\ProductService;
use PHPUnit\Framework\TestCase;

final class ProductServiceTest extends TestCase
{
    private function categoryRepo(): CategoryRepositoryInterface
    {
        $repo = $this->createMock(CategoryRepositoryInterface::class);
        $repo->method('findById')->willReturn(new Category(1, 'Elektronik'));

        return $repo;
    }

    public function testCreateRejectsDuplicateSku(): void
    {
        $products = new InMemoryProductRepository();
        $products->save(new Product(null, 'DUP-1', 'Existing', 1, 'pcs', 100, 150, 5));

        $service = new ProductService($products, $this->categoryRepo(), sys_get_temp_dir());

        $this->expectException(ValidationException::class);
        $service->create([
            'sku' => 'DUP-1',
            'name' => 'New Product',
            'category_id' => 1,
            'purchase_price' => 100,
            'selling_price' => 150,
            'reorder_point' => 5,
        ]);
    }

    public function testCreateSucceedsWithValidData(): void
    {
        $products = new InMemoryProductRepository();
        $service = new ProductService($products, $this->categoryRepo(), sys_get_temp_dir());

        $product = $service->create([
            'sku' => 'NEW-1',
            'name' => 'New Product',
            'category_id' => 1,
            'purchase_price' => 100,
            'selling_price' => 150,
            'reorder_point' => 5,
        ]);

        $this->assertNotNull($product->id);
        $this->assertSame('NEW-1', $product->sku);
    }

    public function testDeleteFallsBackToDeactivateWhenUsedInOrders(): void
    {
        $products = new InMemoryProductRepository();
        $product = $products->save(new Product(null, 'USED-1', 'Used Product', 1, 'pcs', 100, 150, 5));
        $products->markUsedInOrders($product->id);

        $service = new ProductService($products, $this->categoryRepo(), sys_get_temp_dir());
        $hardDeleted = $service->delete($product->id);

        $this->assertFalse($hardDeleted);
        $this->assertFalse($products->findById($product->id)->isActive);
    }
}
