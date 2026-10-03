<?php

declare(strict_types=1);

namespace Tests\E2E;

final class EmptyStatesAndFailuresE2ETest extends E2ETestCase
{
    private function clearTable(string $table): void
    {
        $this->db()->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->db()->exec('DELETE FROM ' . $table);
        $this->db()->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function testListPagesShowEmptyStateWhenThereAreNoRows(): void
    {
        $admin = $this->loginAs(self::ADMIN);
        foreach (['categories', 'customers', 'suppliers', 'warehouses'] as $table) {
            $this->clearTable($table);
            $response = $admin->get('/' . $table);

            $this->assertSame(200, $response->status, $table);
            $this->assertStringContainsString('empty-state', $response->body, $table);
        }

        $this->clearTable('products');
        $this->assertStringContainsString('empty-state', $admin->get('/products')->body);
        $this->assertStringContainsString('empty-state', $admin->get('/products?search=zzz-no-match')->body);
    }

    public function testProductListShowsManageActionsAndPagerEdges(): void
    {
        $admin = $this->loginAs(self::ADMIN);

        $first = $admin->get('/products')->body;
        $this->assertStringContainsString('Deactivate', $first);
        $this->assertStringContainsString('Next', $first);

        $middle = $admin->get('/products?page=2')->body;
        $this->assertStringContainsString('Prev', $middle);

        $last = $admin->get('/products?page=4')->body;
        $this->assertStringContainsString('class="disabled">Next', $last);
        $this->assertStringNotContainsString('Deactivate', $this->loginAs(self::SALES)->get('/products')->body);
    }

    public function testDetailPagesHandleMissingItemsStockAndProductImage(): void
    {
        $admin = $this->loginAs(self::ADMIN);

        $this->clearTable('purchase_order_items');
        $this->clearTable('sales_order_items');
        $this->assertStringContainsString('Belum ada item', $admin->get('/purchase-orders/1')->body);
        $this->assertStringContainsString('Belum ada item', $admin->get('/sales-orders/1')->body);

        $this->clearTable('product_stocks');
        $this->db()->exec("UPDATE products SET image_path = 'sample.png' WHERE id = 1");
        $product = $admin->get('/products/1')->body;
        $this->assertStringContainsString('Belum ada data stok', $product);
        $this->assertStringContainsString('/uploads/sample.png', $product);
    }

    public function testDashboardShowsEmptyLowStockStateWhenEverythingIsAboveReorderPoint(): void
    {
        $this->db()->exec('UPDATE product_stocks SET quantity = 100000');

        $body = $this->loginAs(self::ADMIN)->get('/dashboard')->body;

        $this->assertStringContainsString('Semua produk berada di atas titik pemesanan ulang', $body);
    }

    public function testDatabaseFailuresNeverLeakDetailsAndReturnGenericErrors(): void
    {
        $admin = $this->loginAs(self::ADMIN);
        $pdo = $this->db();

        $pdo->exec('RENAME TABLE purchase_orders TO purchase_orders_bak');
        try {
            $wrapped = $admin->get('/purchase-orders');
            $this->assertSame(500, $wrapped->status);
            $this->assertStringContainsString('unexpected error', $wrapped->body);
            $this->assertStringNotContainsString('purchase_orders', $wrapped->body);

            $outsideHandler = $admin->get('/purchase-orders/1');
            $this->assertSame(500, $outsideHandler->status);
            $this->assertStringNotContainsString('purchase_orders', $outsideHandler->body);
        } finally {
            $pdo->exec('RENAME TABLE purchase_orders_bak TO purchase_orders');
        }

        $pdo->exec('RENAME TABLE products TO products_bak');
        try {
            $api = $admin->get('/api/products/SKU-0001/availability');
            $this->assertSame(500, $api->status);
            $this->assertSame('An unexpected error occurred.', $api->json()['error']);
        } finally {
            $pdo->exec('RENAME TABLE products_bak TO products');
        }
    }

    public function testStoringAnUploadedImageFailsGracefullyWhenTheUploadDirectoryIsReadOnly(): void
    {
        $dir = dirname(__DIR__, 2) . '/build/uploads';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        chmod($dir, 0555);
        if (is_writable($dir)) {
            chmod($dir, 0775);
            $this->markTestSkipped('Upload directory stays writable (running as root); cannot simulate a storage failure.');
        }

        try {
            $client = $this->loginAs(self::ADMIN);
            $response = $client->post('/products', [
                'sku' => 'E2E-RO', 'name' => 'Read only', 'category_id' => '1', 'unit' => 'pcs',
                'purchase_price' => '1', 'selling_price' => '2', 'reorder_point' => '1',
                'image' => $this->upload($this->png(), 'ro.png', 'image/png'),
            ]);

            $this->assertStringEndsWith('/products/create', $response->location);
            $this->assertStringContainsString('Failed to store', $client->get('/products/create')->body);
        } finally {
            chmod($dir, 0775);
        }
    }

    public function testUserPasswordRulesAndPasswordChangeAreEnforced(): void
    {
        $admin = $this->loginAs(self::ADMIN);
        $base = ['name' => 'Pw User', 'email' => 'pw.user@ioms.test', 'role' => 'Sales', 'is_active' => '1'];

        $admin->post('/users', array_merge($base, ['password' => '']));
        $this->assertStringContainsString('Password is required', $admin->get('/users/create')->body);

        $duplicate = $admin->post('/users', array_merge($base, ['email' => self::ADMIN, 'password' => 'Secret123!']));
        $this->assertStringEndsWith('/users/create', $duplicate->location);
        $this->assertStringContainsString('already registered', $admin->get('/users/create')->body);

        $admin->post('/users', array_merge($base, ['password' => 'Secret123!']));
        $id = (int) $this->value("SELECT id FROM users WHERE email = 'pw.user@ioms.test'");

        $tooShort = $admin->post('/users/' . $id, array_merge($base, ['password' => 'short', '_method' => 'PUT']));
        $this->assertStringEndsWith('/users/' . $id . '/edit', $tooShort->location);

        $admin->post('/users/' . $id, array_merge($base, ['password' => 'BrandNew456!', '_method' => 'PUT']));
        $fresh = $this->client();
        $this->assertStringEndsWith('/dashboard', $fresh->post('/login', ['email' => 'pw.user@ioms.test', 'password' => 'BrandNew456!'])->location);
        $this->assertStringEndsWith('/login', $this->client()->post('/login', ['email' => 'pw.user@ioms.test', 'password' => 'Secret123!'])->location);
    }

    public function testCategoryInUseCannotBeDeleted(): void
    {
        $admin = $this->loginAs(self::ADMIN);

        $response = $admin->post('/categories/1/delete');

        $this->assertSame(302, $response->status);
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM categories WHERE id = 1'));
    }

    public function testReceivingMoreOfAnAlreadyCompleteLineAddsNothing(): void
    {
        $client = $this->loginAs(self::ADMIN);
        $client->post('/purchase-orders', [
            'po_number' => 'PO-E2E-CAP', 'supplier_id' => '1', 'warehouse_id' => '1', 'order_date' => date('Y-m-d'),
            'items' => [['product_id' => '1', 'qty_ordered' => '5', 'purchase_price' => '1'], ['product_id' => '2', 'qty_ordered' => '5', 'purchase_price' => '1']],
        ]);
        $id = (int) $this->value('SELECT MAX(id) FROM purchase_orders');
        $items = array_column($this->rows('SELECT id FROM purchase_order_items WHERE purchase_order_id = ? ORDER BY id', [$id]), 'id');
        $client->post('/purchase-orders/' . $id . '/order');

        $client->post('/purchase-orders/' . $id . '/receive', ['items' => [$items[0] => '5']]);
        $stock = (int) $this->value('SELECT quantity FROM product_stocks WHERE product_id = 1 AND warehouse_id = 1');
        $client->post('/purchase-orders/' . $id . '/receive', ['items' => [$items[0] => '3']]);

        $this->assertSame($stock, (int) $this->value('SELECT quantity FROM product_stocks WHERE product_id = 1 AND warehouse_id = 1'));
        $this->assertSame('PartiallyReceived', $this->value('SELECT status FROM purchase_orders WHERE id = ?', [$id]));
    }
}
