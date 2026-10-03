<?php

declare(strict_types=1);

namespace Tests\E2E;

final class ProductE2ETest extends E2ETestCase
{
    private function validProduct(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'E2E-001',
            'name' => 'E2E Product',
            'category_id' => '1',
            'unit' => 'pcs',
            'purchase_price' => '1000',
            'selling_price' => '1500',
            'reorder_point' => '5',
            'is_active' => '1',
        ], $overrides);
    }

    public function testListSupportsSearchCategoryStatusSortAndPaging(): void
    {
        $client = $this->loginAs(self::SALES);

        $this->assertStringContainsString('Laptop Asus', $client->get('/products?search=Laptop')->body);
        $this->assertSame(200, $client->get('/products?category_id=2&sort=name_desc')->status);
        $this->assertSame(200, $client->get('/products?status=low')->status);
        $this->assertSame(200, $client->get('/products?status=normal&page=2')->status);
        $this->assertSame(200, $client->get('/products?status=other')->status);
    }

    public function testShowRendersProductAndMissingProductIs404(): void
    {
        $client = $this->loginAs(self::WAREHOUSE);

        $this->assertStringContainsString('SKU-0001', $client->get('/products/1')->body);
        $this->assertSame(404, $client->get('/products/99999')->status);
        $this->assertSame(404, $client->get('/products/99999/edit')->status);
    }

    public function testCreateWithoutImageThenWithEachAllowedImageType(): void
    {
        $client = $this->loginAs(self::WAREHOUSE);
        $this->assertSame(200, $client->get('/products/create')->status);

        $plain = $client->post('/products', $this->validProduct());
        $this->assertSame(302, $plain->status);
        $this->assertStringEndsWith('/products', $plain->location);

        $images = [
            ['E2E-PNG', $this->png(), 'a.png', 'image/png'],
            ['E2E-JPG', $this->jpeg(), 'a.jpg', 'image/jpeg'],
            ['E2E-WEBP', $this->webp(), 'a.webp', 'image/webp'],
        ];
        foreach ($images as [$sku, $bytes, $name, $mime]) {
            $response = $client->post('/products', $this->validProduct(['sku' => $sku, 'image' => $this->upload($bytes, $name, $mime)]));
            $this->assertSame(302, $response->status, $sku);
            $this->assertStringEndsWith('/products', $response->location, $sku);
            $this->assertNotNull($this->value('SELECT image_path FROM products WHERE sku = ?', [$sku]), $sku);
        }
    }

    public function testInvalidImagesAreRejected(): void
    {
        $client = $this->loginAs(self::ADMIN);

        $wrongType = $client->post('/products', $this->validProduct(['image' => $this->upload('plain text', 'a.txt', 'text/plain')]));
        $this->assertStringEndsWith('/products/create', $wrongType->location);
        $this->assertStringContainsString('JPEG, PNG, or WEBP', $client->get('/products/create')->body);

        $tooBig = $client->post('/products', $this->validProduct(['image' => $this->upload(str_repeat('a', 2_500_000), 'big.png', 'image/png')]));
        $this->assertStringEndsWith('/products/create', $tooBig->location);
        $this->assertStringContainsString('at most 2MB', $client->get('/products/create')->body);

        $failed = $client->post('/products', $this->validProduct(['image' => $this->upload(str_repeat('a', 4_000_000), 'huge.png', 'image/png')]));
        $this->assertStringEndsWith('/products/create', $failed->location);
        $this->assertStringContainsString('upload failed', $client->get('/products/create')->body);

        $this->assertSame(0, (int) $this->value("SELECT COUNT(*) FROM products WHERE sku = 'E2E-001'"));
    }

    public function testInvalidFieldsAndDuplicateSkuAreRejected(): void
    {
        $client = $this->loginAs(self::ADMIN);

        $invalid = $client->post('/products', $this->validProduct([
            'sku' => '', 'name' => '', 'category_id' => '999', 'purchase_price' => '-1', 'selling_price' => 'abc', 'reorder_point' => '-3',
        ]));
        $this->assertStringEndsWith('/products/create', $invalid->location);
        $page = $client->get('/products/create');
        foreach (['SKU is required', 'Name is required', 'valid category', 'Purchase price', 'Selling price', 'Reorder point'] as $message) {
            $this->assertStringContainsString($message, $page->body);
        }

        $duplicate = $client->post('/products', $this->validProduct(['sku' => 'SKU-0001']));
        $this->assertStringEndsWith('/products/create', $duplicate->location);
        $this->assertStringContainsString('already exists', $client->get('/products/create')->body);
    }

    public function testUpdateChangesFieldsAcceptsNewImageAndRejectsBadInput(): void
    {
        $client = $this->loginAs(self::ADMIN);
        $this->assertSame(200, $client->get('/products/1/edit')->status);

        $ok = $client->post('/products/1', $this->validProduct(['sku' => 'SKU-0001', 'name' => 'Renamed Laptop', '_method' => 'PUT', 'image' => $this->upload($this->png(), 'n.png', 'image/png')]));
        $this->assertSame(302, $ok->status);
        $this->assertSame('Renamed Laptop', $this->value('SELECT name FROM products WHERE id = 1'));
        $this->assertNotNull($this->value('SELECT image_path FROM products WHERE id = 1'));

        $bad = $client->post('/products/1', $this->validProduct(['sku' => '', '_method' => 'PUT']));
        $this->assertStringEndsWith('/products/1/edit', $bad->location);

        $missing = $client->post('/products/99999', $this->validProduct(['_method' => 'PUT']));
        $this->assertSame(302, $missing->status);
        $this->assertSame(302, $client->post('/products/99999', $this->validProduct())->status);
    }

    public function testDeleteDeactivatesProductWhetherOrNotItIsUsedInOrders(): void
    {
        $client = $this->loginAs(self::ADMIN);
        $client->post('/products', $this->validProduct());
        $unused = (int) $this->value("SELECT id FROM products WHERE sku = 'E2E-001'");

        $this->assertSame(302, $client->post('/products/' . $unused . '/delete')->status);
        $this->assertSame(0, (int) $this->value('SELECT is_active FROM products WHERE id = ?', [$unused]));

        $this->assertSame(302, $client->post('/products/1/delete')->status);
        $this->assertSame(0, (int) $this->value('SELECT is_active FROM products WHERE id = 1'));

        $this->assertSame(302, $client->post('/products/99999/delete')->status);
        $this->assertSame(302, $client->request('DELETE', '/products/2')->status);
    }

    public function testProductWritesAreRestrictedByRole(): void
    {
        $sales = $this->loginAs(self::SALES);
        $this->assertSame(403, $sales->get('/products/create')->status);
        $this->assertSame(403, $sales->post('/products', $this->validProduct())->status);
        $this->assertSame(403, $sales->get('/products/1/edit')->status);
        $this->assertSame(403, $sales->post('/products/1', $this->validProduct(['_method' => 'PUT']))->status);

        $warehouse = $this->loginAs(self::WAREHOUSE);
        $this->assertSame(403, $warehouse->post('/products/1/delete')->status);
    }
}
