<?php

declare(strict_types=1);

namespace Tests\E2E;

/**
 * Search, status filter and header sorting on the master data lists, plus header sorting on the
 * product and order lists (FIND-01).
 */
final class ListFilterSortE2ETest extends E2ETestCase
{
    private function body(string $path): string
    {
        return $this->loginAs(self::ADMIN)->get($path)->body;
    }

    /** @param list<string> $names */
    private function assertOrder(string $body, array $names): void
    {
        $last = -1;
        foreach ($names as $name) {
            $position = strpos($body, $name);
            $this->assertNotFalse($position, "{$name} is missing");
            $this->assertGreaterThan($last, $position, "{$name} is out of order");
            $last = (int) $position;
        }
    }

    public function testMasterListsAreSortedByNameAndEveryHeaderSortsByLink(): void
    {
        foreach (['/categories', '/warehouses', '/suppliers', '/customers', '/users'] as $path) {
            $body = $this->body($path);

            $this->assertStringContainsString('aria-sort="ascending"', $body, $path);
            $this->assertStringContainsString('href="' . $path . '?sort=name_desc"', $body, $path);
            $this->assertStringNotContainsString('<select id="sort"', $body, $path);
        }

        $this->assertOrder($this->body('/suppliers'), ['CV Jaya Abadi', 'PT Global Elektronik', 'UD Bangun Sentosa']);
    }

    public function testSortingByAHeaderReordersTheRows(): void
    {
        $descending = $this->body('/suppliers?sort=name_desc');
        $this->assertOrder($descending, ['UD Bangun Sentosa', 'PT Sumber Makmur', 'CV Jaya Abadi']);
        $this->assertStringContainsString('aria-sort="descending"', $descending);
        $this->assertStringContainsString('href="/suppliers?sort=name_asc"', $descending);

        $this->assertOrder($this->body('/users?sort=email_desc'), ['sari.sales@ioms.test', 'rudi.warehouse@ioms.test', 'admin@ioms.test']);
        $this->assertOrder($this->body('/warehouses?sort=location_asc'), ['Gudang Pusat Jakarta', 'Gudang Cabang Surabaya']);
    }

    public function testSearchFiltersEveryMasterList(): void
    {
        $cases = [
            ['/suppliers?search=BANGUN', 'UD Bangun Sentosa', 'CV Jaya Abadi'],
            ['/customers?search=toko', 'Toko Sinar Jaya', 'PT Bintang Terang'],
            ['/categories?search=bahan', 'Bahan Bangunan', 'Elektronik'],
            ['/warehouses?search=surabaya', 'Gudang Cabang Surabaya', 'Gudang Pusat Jakarta'],
            ['/users?search=warehouse', 'rudi.warehouse@ioms.test', 'admin@ioms.test'],
        ];
        foreach ($cases as [$path, $shown, $hidden]) {
            $body = $this->body($path);

            $this->assertStringContainsString($shown, $body, $path);
            $this->assertStringNotContainsString($hidden, $body, $path);
        }
    }

    public function testStatusFilterKeepsActiveOrInactiveRows(): void
    {
        $this->db()->exec("UPDATE suppliers SET is_active = 0 WHERE name = 'CV Jaya Abadi'");
        $this->db()->exec("UPDATE customers SET is_active = 0 WHERE name = 'Toko Sinar Jaya'");
        $this->db()->exec("UPDATE warehouses SET is_active = 0 WHERE name = 'Gudang Pusat Jakarta'");
        $this->db()->exec("UPDATE users SET is_active = 0 WHERE email = 'maya.warehouse@ioms.test'");
        $cases = [
            ['/suppliers', 'CV Jaya Abadi', 'PT Global Elektronik'],
            ['/customers', 'Toko Sinar Jaya', 'PT Bintang Terang'],
            ['/warehouses', 'Gudang Pusat Jakarta', 'Gudang Cabang Surabaya'],
            ['/users', 'maya.warehouse@ioms.test', 'admin@ioms.test'],
        ];
        foreach ($cases as [$path, $inactive, $active]) {
            $onlyInactive = $this->body($path . '?status=inactive');
            $this->assertStringContainsString($inactive, $onlyInactive, $path);
            $this->assertStringNotContainsString($active, $onlyInactive, $path);

            $onlyActive = $this->body($path . '?status=active');
            $this->assertStringContainsString($active, $onlyActive, $path);
            $this->assertStringNotContainsString($inactive, $onlyActive, $path);
        }
    }

    public function testEveryFilterBarHasTheSameSearchBoxWithClearButtonAndApplyReset(): void
    {
        $lists = [
            '/categories', '/warehouses', '/suppliers', '/customers', '/users', '/products', '/purchase-orders', '/sales-orders',
        ];
        foreach ($lists as $path) {
            foreach ([$path, $path . '?search=abc'] as $url) {
                $body = $this->body($url);

                $this->assertSame(1, substr_count($body, 'class="search-box"'), $url);
                $this->assertStringContainsString('type="search"', $body, $url);
                $this->assertStringContainsString('aria-label="Clear search"', $body, $url);
                $this->assertStringContainsString('<div class="field filter-actions">', $body, $url);
            }
            $this->assertStringNotContainsString('Reset', $this->body($path), $path);
            $this->assertStringContainsString('<a class="btn btn-secondary" href="' . $path . '">', $this->body($path . '?search=abc'), $path);
        }
    }

    public function testResetIsOnlyShownWhileAFilterIsOn(): void
    {
        $this->assertStringNotContainsString('Reset', $this->body('/suppliers'));
        $this->assertStringNotContainsString('Reset', $this->body('/suppliers?sort=name_desc'), 'sorting alone is not a filter');
        $this->assertStringNotContainsString('Reset', $this->body('/products?sort=sku_asc'));

        $filters = [
            '/suppliers?status=active', '/customers?search=toko', '/products?category_id=1', '/products?status=low',
            '/purchase-orders?status=Draft', '/purchase-orders?supplier_id=1', '/sales-orders?customer_id=1',
        ];
        foreach ($filters as $url) {
            $body = $this->body($url);

            $this->assertStringContainsString('Reset</a>', $body, $url);
            $this->assertStringContainsString('<a class="btn btn-secondary" href="' . strstr($url, '?', true) . '">', $body, $url);
        }
    }

    public function testClearButtonIsHiddenUntilThereIsSearchText(): void
    {
        $this->assertStringContainsString('class="search-clear" aria-label="Clear search" hidden', $this->body('/suppliers'));
        $this->assertStringNotContainsString('aria-label="Clear search" hidden', $this->body('/suppliers?search=abc'));
    }

    public function testFilterFormKeepsTheSortAndResetGoesBackToTheFullList(): void
    {
        $filtered = $this->body('/suppliers?search=pt&status=active&sort=name_desc');

        $this->assertStringContainsString('<input type="hidden" name="sort" value="name_desc">', $filtered);
        $this->assertStringContainsString('<a class="btn btn-secondary" href="/suppliers">', $filtered);
        $this->assertStringContainsString('search=pt&amp;status=active&amp;sort=contact_asc', $filtered);
        $this->assertStringContainsString('<input type="hidden" name="sort" value="name_asc">', $this->body('/suppliers'));
    }

    public function testUnknownFilterAndSortValuesFallBackToTheDefaults(): void
    {
        $body = $this->body('/suppliers?status=bogus&sort=bogus_sideways');

        $this->assertOrder($body, ['CV Jaya Abadi', 'PT Global Elektronik', 'UD Bangun Sentosa']);
        $this->assertStringContainsString('aria-sort="ascending"', $body);
        $this->assertStringContainsString('<input type="hidden" name="sort" value="name_asc">', $body);
        $this->assertStringContainsString('PT Sumber Makmur', $body);
    }

    public function testEmptyResultExplainsTheFilterInsteadOfTheEmptyDatabase(): void
    {
        $body = $this->body('/suppliers?search=zzzzzz');

        $this->assertStringContainsString('No results found', $body);
        $this->assertStringNotContainsString('No suppliers yet', $body);
        $this->assertStringNotContainsString('Reset filters', $body);
        $this->assertStringContainsString('<a class="btn btn-secondary" href="/suppliers">', $body);
    }

    public function testProductHeadersSortByColumnAndTheSortDropdownIsGone(): void
    {
        $columns = ['sku', 'purchase_price', 'selling_price', 'reorder_point'];
        foreach ($columns as $column) {
            foreach (['asc', 'desc'] as $direction) {
                $body = $this->body("/products?sort={$column}_{$direction}");
                $this->assertStringContainsString('aria-sort="' . ($direction === 'asc' ? 'ascending' : 'descending') . '"', $body, "{$column} {$direction}");
            }
        }
        $this->assertStringNotContainsString('<select id="sort"', $this->body('/products'));

        $sku = $this->db()->query('SELECT sku FROM products ORDER BY selling_price DESC, name ASC LIMIT 1')->fetchColumn();
        $this->assertSame(1, preg_match('#<td>(SKU-\d+)</td>#', $this->body('/products?sort=selling_price_desc'), $first));
        $this->assertSame($sku, $first[1]);
        $names = $this->db()->query('SELECT name FROM products ORDER BY name DESC LIMIT 2')->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertOrder($this->body('/products?sort=name_desc'), $names);
    }

    public function testOrderListsSortByEveryColumnInBothDirections(): void
    {
        $lists = [
            '/purchase-orders' => ['purchase_orders', 'po_number', 'suppliers', 'supplier_id', 'PO-'],
            '/sales-orders' => ['sales_orders', 'so_number', 'customers', 'customer_id', 'SO-'],
        ];
        foreach ($lists as $path => [$table, $number, $partyTable, $partyKey, $prefix]) {
            $columns = ['number' => "t.{$number}", 'party' => 'p.name', 'date' => 't.order_date', 'status' => 't.status'];
            foreach ($columns as $column => $sql) {
                foreach (['asc', 'desc'] as $direction) {
                    $order = "{$sql} {$direction}, " . ($column === 'date'
                        ? "t.created_at {$direction}"
                        : 't.order_date DESC, t.created_at DESC');
                    $expected = $this->db()->query(
                        "SELECT t.{$number} FROM {$table} t JOIN {$partyTable} p ON p.id = t.{$partyKey} ORDER BY {$order} LIMIT 1"
                    )->fetchColumn();

                    $body = $this->body("{$path}?sort={$column}_{$direction}");

                    $this->assertSame(1, preg_match('#<td>(' . $prefix . '[\d-]+)</td>#', $body, $first), "{$path} {$column} {$direction}");
                    $this->assertSame($expected, $first[1], "{$path} {$column} {$direction}");
                    $this->assertStringContainsString('aria-sort="' . ($direction === 'asc' ? 'ascending' : 'descending') . '"', $body);
                }
            }
            $this->assertStringContainsString('aria-sort="descending"', $this->body($path . '?sort=bogus_asc'));
        }
    }

    public function testProductUnitAndStatusHeadersSortToo(): void
    {
        $this->db()->exec('UPDATE products SET is_active = 0 WHERE id = 5');
        $inactive = $this->db()->query('SELECT sku FROM products WHERE id = 5')->fetchColumn();

        $asc = $this->body('/products?sort=status_asc');
        $this->assertSame(1, preg_match('#<td>(SKU-\d+)</td>#', $asc, $first));
        $this->assertSame($inactive, $first[1]);

        $desc = $this->body('/products?sort=status_desc');
        $this->assertSame(1, preg_match('#<td>(SKU-\d+)</td>#', $desc, $first));
        $this->assertNotSame($inactive, $first[1]);

        foreach (['asc', 'desc'] as $direction) {
            $expected = $this->db()->query("SELECT sku FROM products ORDER BY unit {$direction}, name ASC LIMIT 1")->fetchColumn();
            $this->assertSame(1, preg_match('#<td>(SKU-\d+)</td>#', $this->body("/products?sort=unit_{$direction}"), $first));
            $this->assertSame($expected, $first[1], "unit {$direction}");
        }
        $this->assertStringContainsString('sort=status_asc', $this->body('/products'));
        $this->assertStringContainsString('sort=unit_asc', $this->body('/products'));
    }

    public function testActiveFlagIsASwitchOnEveryFormThatHasIt(): void
    {
        foreach (['/users/create', '/users/2/edit', '/warehouses/create', '/warehouses/1/edit', '/products/create', '/products/1/edit'] as $path) {
            $body = $this->body($path);

            $this->assertStringContainsString('class="switch"', $body, $path);
            $this->assertStringContainsString('class="switch-track"', $body, $path);
            $this->assertSame(1, substr_count($body, 'name="is_active" value="0"'), $path);
            $this->assertStringNotContainsString('checkbox-field', $body, $path);
        }
        $this->assertMatchesRegularExpression('#id="is_active" name="is_active" value="1" checked#', $this->body('/products/1/edit'));

        $this->db()->exec('UPDATE products SET is_active = 0 WHERE id = 1');
        $this->assertDoesNotMatchRegularExpression('#name="is_active" value="1" checked#', $this->body('/products/1/edit'));
    }

    public function testProductDetailShowsSummaryInformationAndPricingPanels(): void
    {
        $category = $this->db()->query('SELECT c.name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.id = 1')->fetchColumn();

        $body = $this->body('/products/1');

        foreach (['Total Stock', 'Reorder Point', 'Stock Value', 'Product Information', 'Pricing', 'Margin per Unit', 'Stock per Warehouse'] as $text) {
            $this->assertStringContainsString($text, $body, $text);
        }
        $this->assertStringContainsString('<dd>' . $category . '</dd>', $body);
        $this->assertStringContainsString('<strong>56</strong>', $body);
        $this->assertSame(1, preg_match('#Margin per Unit</dt><dd>Rp ([\d.]+) <span class="text-muted">\((\d+)%\)#', $body, $margin));
        $this->assertSame('Rp 1.300.000', 'Rp ' . $margin[1]);
        $this->assertSame('24', $margin[2]);
    }

    public function testOrderStatusBadgesAreSpacedInListsAndDetails(): void
    {
        $po = (int) $this->db()->query("SELECT id FROM purchase_orders WHERE status = 'PartiallyReceived' LIMIT 1")->fetchColumn();
        $so = (int) $this->db()->query("SELECT id FROM sales_orders WHERE status = 'PendingApproval' LIMIT 1")->fetchColumn();

        $cases = [
            ['/purchase-orders?status=PartiallyReceived', 'badge-partiallyreceived">Partially Received<'],
            ["/purchase-orders/{$po}", 'badge-partiallyreceived">Partially Received<'],
            ['/sales-orders?status=PendingApproval', 'badge-pendingapproval">Pending Approval<'],
            ["/sales-orders/{$so}", 'badge-pendingapproval">Pending Approval<'],
        ];
        foreach ($cases as [$url, $badge]) {
            $body = $this->body($url);

            $this->assertStringContainsString($badge, $body, $url);
            $this->assertDoesNotMatchRegularExpression('#>(PartiallyReceived|PendingApproval)<#', $body, $url);
        }
    }

    public function testOrderListsSortByTheDateHeaderAndKeepItWhenPaging(): void
    {
        foreach (['/purchase-orders' => ['purchase_orders', 'PO-'], '/sales-orders' => ['sales_orders', 'SO-']] as $path => [$table, $prefix]) {
            $oldest = $this->db()->query("SELECT MIN(order_date) FROM {$table}")->fetchColumn();
            foreach (['?sort=date_asc', '?sort=asc'] as $query) {
                $body = $this->body($path . $query);
                $this->assertSame(1, preg_match('#<td>' . $prefix . '[\d-]+</td>\s*<td>[^<]*</td>\s*<td>([\d-]+)</td>#', $body, $first), $path . $query);
                $this->assertSame($oldest, $first[1], $path . $query);
                $this->assertStringContainsString('aria-sort="ascending"', $body);
            }

            $newest = $this->body($path);
            $this->assertStringContainsString('aria-sort="descending"', $newest);
            $this->assertStringContainsString('href="' . $path . '?sort=date_asc"', $newest);
            $this->assertStringNotContainsString('<select id="sort"', $newest);
        }

        $this->assertStringContainsString('sort=date_asc&amp;page=2', $this->body('/purchase-orders?sort=date_asc'));
    }
}
