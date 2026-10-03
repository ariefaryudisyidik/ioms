<?php

declare(strict_types=1);

namespace Tests\E2E;

final class ReportsApiDashboardE2ETest extends E2ETestCase
{
    public function testDashboardRendersForEveryRole(): void
    {
        foreach ([self::ADMIN, self::SALES, self::WAREHOUSE] as $email) {
            $response = $this->loginAs($email)->get('/dashboard');

            $this->assertSame(200, $response->status, $email);
        }
    }

    public function testReportsPageAndCsvExportsAreAvailableToLoggedInUsers(): void
    {
        $client = $this->loginAs(self::ADMIN);
        $this->assertSame(200, $client->get('/reports')->status);

        $ledger = $client->get('/reports/stock-ledger.csv');
        $this->assertSame(200, $ledger->status);
        $this->assertStringContainsString('text/csv', $ledger->contentType);
        $this->assertStringContainsString('Movement Type', $ledger->body);

        $this->assertSame(200, $client->get('/reports/stock-ledger.csv?date_from=2000-01-01&date_to=2999-12-31')->status);
        $this->assertStringContainsString('SO Number', $client->get('/reports/orders.csv')->body);
        $this->assertStringContainsString('PO-2026-0001', $client->get('/reports/orders.csv?type=purchase&date_from=2000-01-01&date_to=2999-12-31')->body);
    }

    public function testSalesExportIsRestrictedToTheRequestersOwnOrders(): void
    {
        $body = $this->loginAs(self::SALES)->get('/reports/orders.csv?type=sales')->body;

        $this->assertStringContainsString('SO-2026-0001', $body);
        $this->assertStringNotContainsString('SO-2026-0002', $body);
        $this->assertSame(302, $this->client()->get('/reports/orders.csv')->status);
        $this->assertSame(302, $this->client()->get('/reports/stock-ledger.csv')->status);
        $this->assertSame(302, $this->client()->get('/reports')->status);
    }

    public function testAvailabilityApiReturnsStockPerWarehouse(): void
    {
        $client = $this->loginAs(self::SALES);

        $found = $client->get('/api/products/SKU-0001/availability');
        $this->assertSame(200, $found->status);
        $data = $found->json();
        $this->assertSame('SKU-0001', $data['sku']);
        $this->assertSame(56, $data['total']);
        $this->assertCount(2, $data['warehouses']);

        $missing = $client->get('/api/products/NOPE/availability');
        $this->assertSame(404, $missing->status);
        $this->assertSame('Not Found', $missing->json()['error']);
    }

    public function testAvailabilityApiShowsUnknownWarehouseAndZeroStockProducts(): void
    {
        $client = $this->loginAs(self::ADMIN);
        $this->db()->exec("INSERT INTO products (id, sku, name, category_id, unit, purchase_price, selling_price, reorder_point) VALUES (900, 'E2E-NOSTOCK', 'No stock', 1, 'pcs', 1, 2, 1)");

        $data = $client->get('/api/products/E2E-NOSTOCK/availability')->json();

        $this->assertSame(0, $data['total']);
        $this->assertSame([], $data['warehouses']);
    }
}
