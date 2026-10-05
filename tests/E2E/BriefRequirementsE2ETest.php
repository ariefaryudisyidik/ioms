<?php

declare(strict_types=1);

namespace Tests\E2E;

/**
 * Requirements from the project brief that the other suites do not pin down
 * explicitly: FIND-01 (order search), WH-01 (stock total), DASH-01 (role dashboards).
 */
final class BriefRequirementsE2ETest extends E2ETestCase
{
    private function card(string $html, string $label): ?string
    {
        $pattern = '#<div class="card-label">(?:<svg.*?</svg>\s*)?<span>' . preg_quote(htmlspecialchars($label), '#') . '</span></div><div class="card-value">([^<]*)</div>#s';

        return preg_match($pattern, $html, $match) === 1 ? $match[1] : null;
    }

    // ---- FIND-01 -------------------------------------------------------------

    public function testPurchaseOrdersCanBeSearchedByNumberOrSupplierName(): void
    {
        $admin = $this->loginAs(self::ADMIN);

        $byNumber = $admin->get('/purchase-orders?search=PO-2026-0003')->body;
        $this->assertStringContainsString('PO-2026-0003', $byNumber);
        $this->assertStringNotContainsString('PO-2026-0001', $byNumber);

        $bySupplier = $admin->get('/purchase-orders?search=Global')->body; // PT Global Elektronik = POs 3, 8, 13
        foreach (['PO-2026-0003', 'PO-2026-0008', 'PO-2026-0013'] as $number) {
            $this->assertStringContainsString($number, $bySupplier);
        }
        $this->assertStringNotContainsString('PO-2026-0001<', $bySupplier);

        $combined = $admin->get('/purchase-orders?search=Global&status=Received&sort=asc')->body;
        $this->assertStringContainsString('PO-2026-0003', $combined);
        $this->assertStringNotContainsString('PO-2026-0009', $combined); // Cancelled PO of another supplier
    }

    public function testSalesOrdersCanBeSearchedAndFilteredByCustomer(): void
    {
        $admin = $this->loginAs(self::ADMIN);

        $byNumber = $admin->get('/sales-orders?search=SO-2026-0002')->body;
        $this->assertStringContainsString('SO-2026-0002', $byNumber);
        $this->assertStringNotContainsString('SO-2026-0001<', $byNumber);

        $byCustomerName = $admin->get('/sales-orders?search=Berkah')->body; // CV Berkah Bersama = SOs 2 and 8
        $this->assertStringContainsString('CV Berkah Bersama', $byCustomerName);
        $this->assertStringContainsString('SO-2026-0008', $byCustomerName);
        $this->assertStringNotContainsString('SO-2026-0001<', $byCustomerName);

        $byFilter = $admin->get('/sales-orders?customer_id=3')->body; // PT Cahaya Abadi = SOs 3 and 9
        $this->assertStringContainsString('SO-2026-0003', $byFilter);
        $this->assertStringNotContainsString('SO-2026-0002<', $byFilter);

        $sari = $this->loginAs(self::SALES)->get('/sales-orders?search=Berkah')->body;
        $this->assertStringNotContainsString('SO-2026-0002', $sari, 'search never exposes other users orders to Sales');
    }

    public function testOrderSearchTreatsWildcardsAsTextAndKeepsFiltersWhenPaging(): void
    {
        $admin = $this->loginAs(self::ADMIN);

        $this->assertStringNotContainsString('PO-2026-0001', $admin->get('/purchase-orders?search=' . urlencode('%'))->body);
        $this->assertStringContainsString('Belum ada data', $admin->get('/sales-orders?search=' . urlencode('SO_2026'))->body);

        $page1 = $admin->get('/purchase-orders?search=PO-2026&sort=asc')->body; // 13 matches, 10 per page
        $this->assertMatchesRegularExpression('#href="[^"]*search=PO-2026[^"]*page=2#', $page1);
        $page2 = $admin->get('/purchase-orders?search=PO-2026&sort=asc&page=2')->body;
        $this->assertStringContainsString('PO-2026-0013', $page2);
        $this->assertStringNotContainsString('PO-2026-0001<', $page2);
    }

    // ---- WH-01 --------------------------------------------------------------

    public function testProductDetailShowsTotalStockAndPerWarehouseBreakdown(): void
    {
        $body = $this->loginAs(self::SALES)->get('/products/1')->body; // SKU-0001: 47 in Jakarta + 9 in Surabaya

        $this->assertStringContainsString('Gudang Pusat Jakarta', $body);
        $this->assertStringContainsString('Gudang Cabang Surabaya', $body);
        $this->assertStringContainsString('<strong>56</strong>', $body);
    }

    // ---- DASH-01 ------------------------------------------------------------

    public function testAdminDashboardShowsInventoryValueComputedFromStock(): void
    {
        $admin = $this->loginAs(self::ADMIN);
        $sql = 'SELECT SUM(ps.quantity * p.purchase_price) FROM product_stocks ps JOIN products p ON p.id = ps.product_id WHERE p.is_active = 1';
        $expected = number_format((float) $this->value($sql), 2);

        $this->assertSame($expected, $this->card($admin->get('/dashboard')->body, 'Inventory Value'));

        $this->db()->exec('UPDATE product_stocks SET quantity = 0');
        $this->assertSame('0.00', $this->card($admin->get('/dashboard')->body, 'Inventory Value'), 'the value is aggregated, not static');
        $this->assertSame('PendingApproval', $this->value('SELECT status FROM sales_orders WHERE id = 1'));
        $this->assertSame('3', $this->card($admin->get('/dashboard')->body, 'SO Pending Approval'));
    }

    public function testSalesDashboardShowsOnlyTheirOwnOrdersPerStatus(): void
    {
        $body = $this->loginAs(self::SALES)->get('/dashboard')->body; // Sari owns SOs 1,3,5,7,9,11

        $this->assertSame('1', $this->card($body, 'My orders: Draft'));
        $this->assertSame('2', $this->card($body, 'My orders: PendingApproval'));
        $this->assertSame('1', $this->card($body, 'My orders: Approved'));
        $this->assertSame('1', $this->card($body, 'My orders: Fulfilled'));
        $this->assertSame('1', $this->card($body, 'My orders: Cancelled'));

        $this->db()->exec("UPDATE sales_orders SET status = 'Draft' WHERE id = 11");
        $this->assertSame('2', $this->card($this->loginAs(self::SALES)->get('/dashboard')->body, 'My orders: Draft'));
    }

    public function testWarehouseDashboardShowsReceiptAndIssueQueuesAndLowStock(): void
    {
        $body = $this->loginAs(self::WAREHOUSE)->get('/dashboard')->body;

        $this->assertSame('3', $this->card($body, 'Awaiting goods receipt (PO Ordered)'));
        $this->assertSame('3', $this->card($body, 'Awaiting goods receipt (PO Partially Received)'));
        $this->assertSame('3', $this->card($body, 'Awaiting goods issue (SO Approved)'));
        $this->assertSame('6', $this->card($body, 'Low Stock Products'));
        $this->assertStringContainsString('SKU-0005', $body);
    }

    // ---- section 1.2: role matrix ---------------------------------------------

    public function testMasterDataIsReadOnlyForSalesAndWarehouseStaff(): void
    {
        foreach ([self::SALES, self::WAREHOUSE] as $email) {
            $client = $this->loginAs($email);
            foreach (['/products/create', '/categories/create', '/warehouses/create', '/suppliers/create', '/customers/create', '/users'] as $path) {
                $this->assertSame(403, $client->get($path)->status, "{$email} {$path}");
            }
        }
        $this->assertSame(200, $this->loginAs(self::SALES)->get('/products')->status);
        $this->assertSame(200, $this->loginAs(self::WAREHOUSE)->get('/products/1')->status);
    }

    public function testPurchaseOrdersAreCreatedByAdminOrWarehouseStaffOnly(): void
    {
        $this->assertSame(200, $this->loginAs(self::ADMIN)->get('/purchase-orders/create')->status);
        $this->assertSame(200, $this->loginAs(self::WAREHOUSE)->get('/purchase-orders/create')->status);
        $this->assertSame(403, $this->loginAs(self::SALES)->get('/purchase-orders/create')->status);
    }
}
