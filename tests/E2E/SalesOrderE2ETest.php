<?php

declare(strict_types=1);

namespace Tests\E2E;

final class SalesOrderE2ETest extends E2ETestCase
{
    private function newSo(array $overrides = []): array
    {
        return array_merge([
            'so_number' => 'SO-E2E-0001',
            'customer_id' => '1',
            'warehouse_id' => '1',
            'order_date' => date('Y-m-d'),
            'items' => [['product_id' => '1', 'qty' => '2', 'selling_price' => '6800000']],
        ], $overrides);
    }

    private function createSo(HttpClient $client, array $overrides = []): int
    {
        $response = $client->post('/sales-orders', $this->newSo($overrides));
        $this->assertSame(302, $response->status);
        $this->assertMatchesRegularExpression('#/sales-orders/\d+$#', $response->location);

        return (int) $this->value('SELECT MAX(id) FROM sales_orders');
    }

    private function orderStatus(int $id): string
    {
        return (string) $this->value('SELECT status FROM sales_orders WHERE id = ?', [$id]);
    }

    private function stock(int $productId, int $warehouseId): int
    {
        return (int) $this->value('SELECT quantity FROM product_stocks WHERE product_id = ? AND warehouse_id = ?', [$productId, $warehouseId]);
    }

    public function testSalesUsersOnlySeeTheirOwnOrdersWhileAdminSeesAll(): void
    {
        $sari = $this->loginAs(self::SALES)->get('/sales-orders?sort=asc')->body;
        $this->assertStringContainsString('SO-2026-0001', $sari);
        $this->assertStringNotContainsString('SO-2026-0002', $sari);

        $admin = $this->loginAs(self::ADMIN);
        $this->assertStringContainsString('SO-2026-0002', $admin->get('/sales-orders?sort=asc')->body);
        $this->assertSame(200, $admin->get('/sales-orders?status=Approved&page=2')->status);
        $this->assertSame(200, $this->loginAs(self::WAREHOUSE)->get('/sales-orders')->status);
    }

    public function testEverySeededSalesOrderRendersItsDetailPage(): void
    {
        foreach ([self::ADMIN, self::SALES, self::WAREHOUSE] as $email) {
            $client = $this->loginAs($email);
            for ($id = 1; $id <= 12; $id++) {
                $this->assertSame(200, $client->get('/sales-orders/' . $id)->status, "{$email} SO {$id}");
            }
        }
        $this->assertSame(404, $this->loginAs(self::ADMIN)->get('/sales-orders/99999')->status);
    }

    public function testCreateFormIsAvailableToSalesAndAdminOnly(): void
    {
        $this->assertSame(200, $this->loginAs(self::SALES)->get('/sales-orders/create')->status);
        $this->assertSame(200, $this->loginAs(self::ADMIN)->get('/sales-orders/create')->status);

        $warehouse = $this->loginAs(self::WAREHOUSE);
        $this->assertSame(403, $warehouse->get('/sales-orders/create')->status);
        $this->assertSame(403, $warehouse->post('/sales-orders', $this->newSo())->status);
    }

    public function testCreateValidationRejectsBadInput(): void
    {
        $client = $this->loginAs(self::SALES);

        $cases = [
            'missing fields' => ['so_number' => '', 'customer_id' => '', 'warehouse_id' => '', 'order_date' => ''],
            'duplicate number' => ['so_number' => 'SO-2026-0001'],
            'no items' => ['items' => []],
            'bad item' => ['items' => [['product_id' => '', 'qty' => '0']]],
        ];
        foreach ($cases as $label => $overrides) {
            $response = $client->post('/sales-orders', $this->newSo($overrides));
            $this->assertStringEndsWith('/sales-orders/create', $response->location, $label);
            $this->assertMatchesRegularExpression('/field-error|form-errors/', $client->get('/sales-orders/create')->body, $label);
        }
        $this->assertSame(12, (int) $this->value('SELECT COUNT(*) FROM sales_orders'));
    }

    public function testFullLifecycleDraftSubmitApproveFulfillMovesStockAndWritesLedger(): void
    {
        $sari = $this->loginAs(self::SALES);
        $admin = $this->loginAs(self::ADMIN);
        $warehouse = $this->loginAs(self::WAREHOUSE);
        $id = $this->createSo($sari);
        $before = $this->stock(1, 1);

        $sari->post('/sales-orders/' . $id . '/submit');
        $this->assertSame('PendingApproval', $this->orderStatus($id));

        $admin->post('/sales-orders/' . $id . '/approve');
        $this->assertSame('Approved', $this->orderStatus($id));
        $this->assertSame(1, (int) $this->value('SELECT approved_by FROM sales_orders WHERE id = ?', [$id]));

        $warehouse->post('/sales-orders/' . $id . '/fulfill');
        $this->assertSame('Fulfilled', $this->orderStatus($id));
        $this->assertSame($before - 2, $this->stock(1, 1));
        $this->assertSame(1, (int) $this->value("SELECT COUNT(*) FROM stock_ledger WHERE reference_type = 'sales_order' AND reference_id = ? AND movement_type = 'Issue'", [$id]));
        $this->assertStringContainsString('Fulfilled', $admin->get('/sales-orders/' . $id)->body);
    }

    public function testRejectReturnsOrderToDraftAndCancelWorksUntilFulfilled(): void
    {
        $sari = $this->loginAs(self::SALES);
        $admin = $this->loginAs(self::ADMIN);
        $id = $this->createSo($sari);

        $sari->post('/sales-orders/' . $id . '/submit');
        $admin->post('/sales-orders/' . $id . '/reject');
        $this->assertSame('Draft', $this->orderStatus($id));

        $sari->post('/sales-orders/' . $id . '/cancel');
        $this->assertSame('Cancelled', $this->orderStatus($id));

        $again = $sari->post('/sales-orders/' . $id . '/cancel');
        $this->assertStringEndsWith('/sales-orders/' . $id, $again->location);
        $this->assertStringContainsString('alert-error', $sari->get('/sales-orders/' . $id)->body);

        $fulfilled = $admin->post('/sales-orders/3/cancel');
        $this->assertSame(302, $fulfilled->status);
        $this->assertSame('Fulfilled', $this->orderStatus(3));
    }

    public function testBusinessRulesBlockSelfApprovalForeignSubmitAndBadTransitions(): void
    {
        $admin = $this->loginAs(self::ADMIN);
        $sari = $this->loginAs(self::SALES);
        $budi = $this->loginAs(self::SALES_2);

        $adminOrder = $this->createSo($admin, ['so_number' => 'SO-E2E-ADMIN']);
        $admin->post('/sales-orders/' . $adminOrder . '/submit');
        $selfApprove = $admin->post('/sales-orders/' . $adminOrder . '/approve');
        $this->assertSame(403, $selfApprove->status);
        $this->assertSame('PendingApproval', $this->orderStatus($adminOrder));

        $sariOrder = $this->createSo($sari, ['so_number' => 'SO-E2E-SARI']);
        $this->assertSame(403, $budi->post('/sales-orders/' . $sariOrder . '/submit')->status);
        $this->assertSame('Draft', $this->orderStatus($sariOrder));

        $this->assertStringEndsWith('/sales-orders/' . $sariOrder, $admin->post('/sales-orders/' . $sariOrder . '/approve')->location);
        $this->assertStringEndsWith('/sales-orders/' . $sariOrder, $admin->post('/sales-orders/' . $sariOrder . '/reject')->location);
        $this->assertStringEndsWith('/sales-orders/' . $sariOrder, $this->loginAs(self::WAREHOUSE)->post('/sales-orders/' . $sariOrder . '/fulfill')->location);
        $this->assertSame('Draft', $this->orderStatus($sariOrder));

        foreach (['submit', 'approve', 'reject', 'cancel', 'fulfill'] as $action) {
            $this->assertSame(302, $admin->post('/sales-orders/99999/' . $action)->status, $action);
        }
    }

    public function testFulfillmentWithInsufficientStockRollsBackAndKeepsOrderApproved(): void
    {
        $sari = $this->loginAs(self::SALES);
        $admin = $this->loginAs(self::ADMIN);
        $id = $this->createSo($sari, ['items' => [['product_id' => '1', 'qty' => '1', 'selling_price' => '1'], ['product_id' => '5', 'qty' => '999', 'selling_price' => '1']]]);
        $sari->post('/sales-orders/' . $id . '/submit');
        $admin->post('/sales-orders/' . $id . '/approve');
        $before = $this->stock(1, 1);

        $response = $this->loginAs(self::WAREHOUSE)->post('/sales-orders/' . $id . '/fulfill');

        $this->assertStringEndsWith('/sales-orders/' . $id, $response->location);
        $this->assertSame('Approved', $this->orderStatus($id));
        $this->assertSame($before, $this->stock(1, 1));
        $this->assertSame(0, (int) $this->value("SELECT COUNT(*) FROM stock_ledger WHERE reference_type = 'sales_order' AND reference_id = ?", [$id]));
    }

    public function testActionsAreRestrictedByRole(): void
    {
        $sales = $this->loginAs(self::SALES);
        $this->assertSame(403, $sales->post('/sales-orders/1/approve')->status);
        $this->assertSame(403, $sales->post('/sales-orders/1/reject')->status);
        $this->assertSame(403, $sales->post('/sales-orders/2/fulfill')->status);

        $warehouse = $this->loginAs(self::WAREHOUSE);
        $this->assertSame(403, $warehouse->post('/sales-orders/1/submit')->status);
        $this->assertSame(403, $warehouse->post('/sales-orders/1/cancel')->status);
        $this->assertSame(403, $warehouse->post('/sales-orders/1/approve')->status);
    }
}
