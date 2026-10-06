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

    public function testFindBySkuReturnsTheMatchingProduct(): void
    {
        $products = new InMemoryProductRepository();
        $products->save(new Product(null, 'FIND-1', 'Findable', 1, 'pcs', 100, 150, 5));
        $service = new ProductService($products, $this->categoryRepo(), sys_get_temp_dir());

        $this->assertSame('Findable', $service->findBySku('FIND-1')?->name);
        $this->assertNull($service->findBySku('MISSING'));
    }

    /**
     * @return array{0:ProductService,1:string,2:string}
     */
    private function serviceWithFreshUploadDir(): array
    {
        $dir = sys_get_temp_dir() . '/ioms-upload-' . bin2hex(random_bytes(4)) . '/nested';
        $service = new ProductService(new InMemoryProductRepository(), $this->categoryRepo(), $dir);

        return [$service, $dir, (string) tempnam(sys_get_temp_dir(), 'img')];
    }

    private function productData(): array
    {
        return ['sku' => 'IMG-1', 'name' => 'With image', 'category_id' => 1, 'purchase_price' => 1, 'selling_price' => 2, 'reorder_point' => 1];
    }

    public function testCreateStoresAnAllowedImageAndCreatesTheUploadDirectory(): void
    {
        [$service, $dir, $tmp] = $this->serviceWithFreshUploadDir();
        file_put_contents($tmp, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

        $product = $service->create($this->productData(), ['error' => UPLOAD_ERR_OK, 'size' => 100, 'tmp_name' => $tmp]);

        $this->assertStringEndsWith('.png', (string) $product->imagePath);
        $this->assertFileExists($dir . '/' . $product->imagePath);
    }

    public function testImageValidationRejectsFailedOversizedAndWrongTypeUploads(): void
    {
        [$service, , $tmp] = $this->serviceWithFreshUploadDir();
        file_put_contents($tmp, 'not an image');

        foreach (
            [
            ['error' => UPLOAD_ERR_INI_SIZE, 'size' => 1, 'tmp_name' => $tmp],
            ['error' => UPLOAD_ERR_OK, 'size' => 3 * 1024 * 1024, 'tmp_name' => $tmp],
            ['error' => UPLOAD_ERR_OK, 'size' => 10, 'tmp_name' => $tmp],
            ] as $file
        ) {
            try {
                $service->create($this->productData(), $file);
                $this->fail('Expected the upload to be rejected.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('image', $e->errors());
            }
        }
    }

    public function testFileWithImageSignatureButNoRealImageIsRejected(): void
    {
        [$service, , $tmp] = $this->serviceWithFreshUploadDir();
        file_put_contents($tmp, "\x89PNG\r\n\x1a\n" . '<?php echo "not an image"; ?>');

        try {
            $service->create($this->productData(), ['error' => UPLOAD_ERR_OK, 'size' => 100, 'tmp_name' => $tmp]);
            $this->fail('A fake image must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('image', $e->errors());
        }
    }

    public function testStorageFailureIsReportedAsAValidationError(): void
    {
        $blocker = (string) tempnam(sys_get_temp_dir(), 'blocker');
        $service = new ProductService(new InMemoryProductRepository(), $this->categoryRepo(), $blocker . '/not-a-dir');
        $tmp = (string) tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

        try {
            @$service->create($this->productData(), ['error' => UPLOAD_ERR_OK, 'size' => 100, 'tmp_name' => $tmp]);
            $this->fail('Expected a storage failure.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Failed to store', $e->errors()['image']);
        }
    }
}
