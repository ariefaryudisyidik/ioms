<?php

declare(strict_types=1);

namespace Tests\E2E;

/**
 * Black-box checks of the application's security controls.
 */
final class SecurityE2ETest extends E2ETestCase
{
    // ---- CSRF -------------------------------------------------------------

    public function testStateChangingRequestsWithoutAValidTokenAreRejected(): void
    {
        $guest = $this->client();
        $this->assertSame(403, $guest->postWithoutToken('/login', ['email' => self::ADMIN, 'password' => self::PASSWORD])->status);
        $this->assertSame(403, $guest->postWithToken('/login', ['email' => self::ADMIN, 'password' => self::PASSWORD], str_repeat('a', 64))->status);

        $admin = $this->loginAs(self::ADMIN);
        $before = (int) $this->value('SELECT COUNT(*) FROM categories');

        $this->assertSame(403, $admin->postWithoutToken('/categories', ['name' => 'Forged'])->status);
        $this->assertSame(403, $admin->postWithoutToken('/sales-orders/1/approve')->status);
        $this->assertSame(403, $admin->postWithoutToken('/users/2/deactivate')->status);
        $this->assertSame(403, $admin->request('DELETE', '/products/1')->status);
        $this->assertSame($before, (int) $this->value('SELECT COUNT(*) FROM categories'));
        $this->assertSame('PendingApproval', $this->value('SELECT status FROM sales_orders WHERE id = 1'));
        $this->assertSame(1, (int) $this->value('SELECT is_active FROM users WHERE id = 2'));
    }

    public function testATokenFromAnotherSessionIsRejected(): void
    {
        $attacker = $this->client();
        $stolen = $attacker->token();
        $victim = $this->loginAs(self::ADMIN);

        $this->assertSame(403, $victim->postWithToken('/categories', ['name' => 'Forged'], $stolen)->status);
        $this->assertSame(302, $victim->post('/categories', ['name' => 'Legit'])->status);
    }

    public function testForgedApiWritesGetJsonForbidden(): void
    {
        $response = $this->loginAs(self::ADMIN)->postWithoutToken('/api/products/SKU-0001/availability');

        $this->assertSame(403, $response->status);
        $this->assertSame('Invalid or missing CSRF token.', $response->json()['error']);
    }

    public function testEveryPostFormCarriesTheTokenAndPagesExposeItInAMetaTag(): void
    {
        $admin = $this->loginAs(self::ADMIN);

        foreach (['/products', '/sales-orders/1', '/purchase-orders/1', '/users', '/categories/create'] as $path) {
            $body = $admin->get($path)->body;
            preg_match_all('/<form\b[^>]*method="post"[^>]*>/i', $body, $forms);
            $this->assertSame(count($forms[0]), substr_count($body, 'name="_csrf"'), $path);
            $this->assertStringContainsString('name="csrf-token"', $body, $path);
        }
        $this->assertStringContainsString('name="_csrf"', $this->client()->get('/login')->body);
    }

    // ---- headers and cookies ---------------------------------------------------

    public function testResponsesCarrySecurityHeadersAndNoPhpVersion(): void
    {
        $response = $this->loginAs(self::ADMIN)->get('/dashboard');

        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->header('X-Frame-Options'));
        $this->assertSame('same-origin', $response->header('Referrer-Policy'));
        $this->assertStringContainsString("default-src 'self'", $response->header('Content-Security-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", $response->header('Content-Security-Policy'));
        $this->assertStringContainsString("script-src 'self'", $response->header('Content-Security-Policy'));
        $this->assertSame('', $response->header('X-Powered-By'));
    }

    public function testSessionCookieIsHttpOnlyAndSameSite(): void
    {
        $cookie = $this->client()->get('/login')->header('Set-Cookie');

        $this->assertStringContainsString('ioms_session=', $cookie);
        $this->assertStringContainsString('HttpOnly', $cookie);
        $this->assertStringContainsString('SameSite=Lax', $cookie);
    }

    // ---- login hardening ------------------------------------------------------

    public function testRepeatedFailedLoginsLockTheAccountEvenForTheRightPassword(): void
    {
        $client = $this->client();
        for ($i = 0; $i < 5; $i++) {
            $client->post('/login', ['email' => self::SALES, 'password' => 'wrong-' . $i]);
        }

        $locked = $client->post('/login', ['email' => self::SALES, 'password' => self::PASSWORD]);
        $this->assertStringEndsWith('/login', $locked->location);
        $this->assertStringContainsString('Too many failed', $client->get('/login')->body);

        $other = $this->client()->post('/login', ['email' => self::ADMIN, 'password' => self::PASSWORD]);
        $this->assertStringEndsWith('/dashboard', $other->location, 'the lock is per account, other accounts can still sign in');
        $this->assertSame(5, (int) $this->value('SELECT COUNT(*) FROM login_attempts'));
    }

    public function testFailedAttemptsAreForgottenAfterASuccessfulLogin(): void
    {
        $client = $this->client();
        for ($i = 0; $i < 3; $i++) {
            $client->post('/login', ['email' => self::SALES, 'password' => 'wrong']);
        }
        $this->assertSame(3, (int) $this->value('SELECT COUNT(*) FROM login_attempts'));

        $client->post('/login', ['email' => self::SALES, 'password' => self::PASSWORD]);

        $this->assertSame(0, (int) $this->value('SELECT COUNT(*) FROM login_attempts'));
    }

    public function testSqlInjectionPayloadsDoNotBypassLoginOrSearch(): void
    {
        $guest = $this->client();
        $response = $guest->post('/login', ['email' => "' OR '1'='1", 'password' => "' OR '1'='1"]);
        $this->assertStringEndsWith('/login', $response->location);

        $admin = $this->loginAs(self::ADMIN);
        $search = $admin->get('/products?search=' . urlencode("' OR 1=1 --"));
        $this->assertSame(200, $search->status);
        $this->assertStringNotContainsString('SKU-0001', $search->body);
        $this->assertSame(32, (int) $this->value('SELECT COUNT(*) FROM products'));
    }

    // ---- session revalidation ---------------------------------------------------

    public function testDeactivatedUserIsSignedOutOnTheNextRequest(): void
    {
        $sari = $this->loginAs(self::SALES);
        $this->assertSame(200, $sari->get('/dashboard')->status);

        $this->db()->exec("UPDATE users SET is_active = 0 WHERE email = '" . self::SALES . "'");

        $response = $sari->get('/dashboard');
        $this->assertSame(302, $response->status);
        $this->assertStringEndsWith('/login', $response->location);
    }

    public function testDeletedUserLosesAccessImmediately(): void
    {
        $budi = $this->loginAs(self::SALES_2);
        $this->db()->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->db()->exec("DELETE FROM users WHERE email = '" . self::SALES_2 . "'");
        $this->db()->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->assertSame(302, $budi->get('/sales-orders')->status);
    }

    public function testRoleChangesTakeEffectWithoutLoggingInAgain(): void
    {
        $sari = $this->loginAs(self::SALES);
        $this->assertSame(200, $sari->get('/sales-orders/create')->status);

        $this->db()->exec("UPDATE users SET role = 'WarehouseStaff' WHERE email = '" . self::SALES . "'");

        $this->assertSame(403, $sari->get('/sales-orders/create')->status);
        $this->assertSame(200, $sari->get('/purchase-orders/create')->status);
    }

    public function testAdminCannotLockThemselvesOut(): void
    {
        $admin = $this->loginAs(self::ADMIN);

        $deactivate = $admin->post('/users/1/deactivate');
        $this->assertStringEndsWith('/users', $deactivate->location);

        $demote = $admin->post('/users/1', ['name' => 'Andi', 'email' => self::ADMIN, 'role' => 'Sales', 'is_active' => '1', '_method' => 'PUT']);
        $this->assertStringEndsWith('/users/1/edit', $demote->location);

        $disable = $admin->post('/users/1', ['name' => 'Andi', 'email' => self::ADMIN, 'role' => 'Admin', 'is_active' => '0', '_method' => 'PUT']);
        $this->assertStringEndsWith('/users/1/edit', $disable->location);

        $this->assertSame('Admin', $this->value('SELECT role FROM users WHERE id = 1'));
        $this->assertSame(1, (int) $this->value('SELECT is_active FROM users WHERE id = 1'));
        $this->assertStringContainsString('cannot deactivate your own account', $admin->get('/users/1/edit')->body);

        $rename = $admin->post('/users/1', ['name' => 'Andi Renamed', 'email' => self::ADMIN, 'role' => 'Admin', 'is_active' => '1', 'password' => '', '_method' => 'PUT']);
        $this->assertStringEndsWith('/users', $rename->location);
        $this->assertSame('Andi Renamed', $this->value('SELECT name FROM users WHERE id = 1'));
    }

    // ---- authorization on data ------------------------------------------------

    public function testReportsAreRestrictedByRole(): void
    {
        $sales = $this->loginAs(self::SALES);
        $this->assertSame(403, $sales->get('/reports/stock-ledger.csv')->status);
        $this->assertSame(403, $sales->get('/reports/orders.csv?type=purchase')->status);
        $this->assertSame(200, $sales->get('/reports/orders.csv?type=sales')->status);

        $warehouse = $this->loginAs(self::WAREHOUSE);
        $this->assertSame(200, $warehouse->get('/reports/stock-ledger.csv')->status);
        $this->assertSame(403, $warehouse->get('/reports/orders.csv?type=purchase')->status);
        $this->assertSame(403, $warehouse->get('/reports/orders.csv?type=sales')->status);

        $unknownType = $sales->get('/reports/orders.csv?type=../../etc/passwd');
        $this->assertSame(200, $unknownType->status);
        $this->assertStringContainsString('SO Number', $unknownType->body);
        $this->assertStringContainsString('sales_orders.csv', $unknownType->header('Content-Disposition'));
    }

    public function testCsvExportNeutralisesSpreadsheetFormulas(): void
    {
        $this->db()->exec("UPDATE sales_orders SET so_number = '=HYPERLINK(\"http://evil\")' WHERE id = 1");
        $this->db()->exec("UPDATE purchase_orders SET po_number = '+cmd|calc' WHERE id = 1");
        $admin = $this->loginAs(self::ADMIN);

        $sales = $admin->get('/reports/orders.csv?type=sales')->body;
        $purchase = $admin->get('/reports/orders.csv?type=purchase')->body;

        $this->assertStringContainsString("'=HYPERLINK", $sales);
        $this->assertStringNotContainsString(',=HYPERLINK', $sales);
        $this->assertStringContainsString("'+cmd|calc", $purchase);
    }

    // ---- input handling -------------------------------------------------------

    public function testUserSuppliedTextIsEscapedInHtmlOutput(): void
    {
        $admin = $this->loginAs(self::ADMIN);
        $payload = '<script>alert("xss")</script>';

        $admin->post('/categories', ['name' => $payload, 'description' => '"><img src=x onerror=alert(1)>']);
        $page = $admin->get('/categories')->body;

        $this->assertStringNotContainsString($payload, $page);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $page);
        $this->assertStringNotContainsString('<img src=x', $page);
    }

    public function testSearchTreatsLikeWildcardsAsPlainText(): void
    {
        $admin = $this->loginAs(self::ADMIN);

        $this->assertStringNotContainsString('SKU-0001', $admin->get('/products?search=' . urlencode('%'))->body);
        $this->assertStringNotContainsString('SKU-0001', $admin->get('/products?search=' . urlencode('SKU_0001'))->body);
        $this->assertStringContainsString('SKU-0001', $admin->get('/products?search=' . urlencode('SKU-0001'))->body);
    }

    public function testOrdersCannotReferenceMissingOrInactiveRecordsOrUseBadValues(): void
    {
        $this->db()->exec('UPDATE warehouses SET is_active = 0 WHERE id = 2');
        $this->db()->exec('UPDATE products SET is_active = 0 WHERE id = 3');
        $admin = $this->loginAs(self::ADMIN);
        $today = date('Y-m-d');

        $po = static fn (array $over): array => array_merge([
            'po_number' => 'PO-SEC-1', 'supplier_id' => '1', 'warehouse_id' => '1', 'order_date' => $today,
            'items' => [['product_id' => '1', 'qty_ordered' => '2', 'purchase_price' => '10']],
        ], $over);
        $so = static fn (array $over): array => array_merge([
            'so_number' => 'SO-SEC-1', 'customer_id' => '1', 'warehouse_id' => '1', 'order_date' => $today,
            'items' => [['product_id' => '1', 'qty' => '2', 'selling_price' => '10']],
        ], $over);

        $badPurchaseOrders = [
            [$po(['supplier_id' => '999']), 'Supplier not found or inactive'],
            [$po(['warehouse_id' => '2']), 'Warehouse not found or inactive'],
            [$po(['items' => [['product_id' => '3', 'qty_ordered' => '2']]]), 'Product not found or inactive'],
            [$po(['items' => [['product_id' => '1', 'qty_ordered' => '2', 'purchase_price' => '-5']]]), 'price must be a non-negative number'],
            [$po(['items' => [['product_id' => '1', 'qty_ordered' => '99999999']]]), 'positive quantity'],
        ];
        foreach ($badPurchaseOrders as $index => [$data, $message]) {
            $this->assertStringEndsWith('/purchase-orders/create', $admin->post('/purchase-orders', $data)->location, "PO case {$index}");
            $this->assertStringContainsString($message, $admin->get('/purchase-orders/create')->body, "PO case {$index}");
        }

        $badSalesOrders = [
            [$so(['customer_id' => '999']), 'Customer not found or inactive'],
            [$so(['warehouse_id' => '2']), 'Warehouse not found or inactive'],
            [$so(['items' => [['product_id' => '3', 'qty' => '2']]]), 'Product not found or inactive'],
            [$so(['items' => [['product_id' => '1', 'qty' => '2', 'selling_price' => '-1']]]), 'price must be a non-negative number'],
            [$so(['order_date' => '2026-02-31']), 'valid date'],
        ];
        foreach ($badSalesOrders as $index => [$data, $message]) {
            $this->assertStringEndsWith('/sales-orders/create', $admin->post('/sales-orders', $data)->location, "SO case {$index}");
            $this->assertStringContainsString($message, $admin->get('/sales-orders/create')->body, "SO case {$index}");
        }

        $this->assertSame(13, (int) $this->value('SELECT COUNT(*) FROM purchase_orders'));
        $this->assertSame(12, (int) $this->value('SELECT COUNT(*) FROM sales_orders'));
    }

    public function testImageWithAnImageSignatureButNoRealImageIsRejected(): void
    {
        $admin = $this->loginAs(self::ADMIN);
        $fake = "\x89PNG\r\n\x1a\n" . '<?php system($_GET["c"]); ?>';

        $response = $admin->post('/products', [
            'sku' => 'E2E-FAKE', 'name' => 'Fake image', 'category_id' => '1', 'unit' => 'pcs',
            'purchase_price' => '1', 'selling_price' => '2', 'reorder_point' => '1',
            'image' => $this->upload($fake, 'shell.png', 'image/png'),
        ]);

        $this->assertStringEndsWith('/products/create', $response->location);
        $this->assertSame(0, (int) $this->value("SELECT COUNT(*) FROM products WHERE sku = 'E2E-FAKE'"));
    }
}
