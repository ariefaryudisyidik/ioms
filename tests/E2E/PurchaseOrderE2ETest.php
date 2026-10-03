<?php

declare(strict_types=1);

namespace Tests\E2E;

final class PurchaseOrderE2ETest extends E2ETestCase
{
    private function newPo(array $overrides = []): array
    {
        return array_merge([
            'po_number' => 'PO-E2E-0001',
            'supplier_id' => '1',
            'warehouse_id' => '1',
            'order_date' => date('Y-m-d'),
            'items' => [['product_id' => '1', 'qty_ordered' => '10', 'purchase_price' => '5500000']],
        ], $overrides);
    }

    private function createPo(\Tests\E2E\HttpClient $client, array $overrides = []): int
    {
        $response = $client->post('/purchase-orders', $this->newPo($overrides));
        $this->assertSame(302, $response->status);
        $this->assertMatchesRegularExpression('#/purchase-orders/\d+$#', $response->location);

        return (int) $this->value('SELECT MAX(id) FROM purchase_orders');
    }

    private function stock(int $productId, int $warehouseId): int
    {
        return (int) $this->value('SELECT quantity FROM product_stocks WHERE product_id = ? AND warehouse_id = ?', [$productId, $warehouseId]);
    }

    public function testListSupportsStatusSupplierSortAndPagingForEveryRole(): void
    {
        foreach ([self::ADMIN, self::SALES, self::WAREHOUSE] as $email) {
            $client = $this->loginAs($email);
            $this->assertStringContainsString('PO-2026-0001', $client->get('/purchase-orders?sort=asc')->body);
            $this->assertSame(200, $client->get('/purchase-orders?status=Received&supplier_id=3')->status);
            $this->assertSame(200, $client->get('/purchase-orders?page=2')->status);
        }
    }

    public function testEverySeededPurchaseOrderRendersItsDetailPage(): void
    {
        $client = $this->loginAs(self::ADMIN);

        for ($id = 1; $id <= 13; $id++) {
            $this->assertSame(200, $client->get('/purchase-orders/' . $id)->status, "PO {$id}");
        }
        $this->assertSame(404, $client->get('/purchase-orders/99999')->status);
        $this->assertSame(404, $client->get('/purchase-orders/99999/receive')->status);
    }

    public function testCreateFormAndSuccessfulCreateAsWarehouseStaff(): void
    {
        $client = $this->loginAs(self::WAREHOUSE);
        $this->assertSame(200, $client->get('/purchase-orders/create')->status);

        $id = $this->createPo($client);

        $this->assertSame('Draft', $this->value('SELECT status FROM purchase_orders WHERE id = ?', [$id]));
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM purchase_order_items WHERE purchase_order_id = ?', [$id]));
        $this->assertStringContainsString('PO-E2E-0001', $client->get('/purchase-orders/' . $id)->body);
    }

    public function testCreateValidationRejectsBadInput(): void
    {
        $client = $this->loginAs(self::ADMIN);

        $cases = [
            'missing fields' => ['po_number' => '', 'supplier_id' => '', 'warehouse_id' => '', 'order_date' => ''],
            'future date' => ['order_date' => '2999-01-01'],
            'bad date' => ['order_date' => '31-31-2026'],
            'duplicate number' => ['po_number' => 'PO-2026-0001'],
            'no items' => ['items' => []],
            'bad item' => ['items' => [['product_id' => '', 'qty_ordered' => '0']]],
        ];
        foreach ($cases as $label => $overrides) {
            $response = $client->post('/purchase-orders', $this->newPo($overrides));
            $this->assertSame(302, $response->status, $label);
            $this->assertStringEndsWith('/purchase-orders/create', $response->location, $label);
            $this->assertMatchesRegularExpression('/field-error|form-errors/', $client->get('/purchase-orders/create')->body, $label);
        }
        $this->assertSame(13, (int) $this->value('SELECT COUNT(*) FROM purchase_orders'));
    }

    public function testOrderThenPartialThenFullReceiptUpdatesStockLedgerAndStatus(): void
    {
        $client = $this->loginAs(self::WAREHOUSE);
        $id = $this->createPo($client, ['items' => [['product_id' => '1', 'qty_ordered' => '10', 'purchase_price' => '1'], ['product_id' => '2', 'qty_ordered' => '5', 'purchase_price' => '1']]]);
        $itemIds = array_column($this->rows('SELECT id FROM purchase_order_items WHERE purchase_order_id = ? ORDER BY id', [$id]), 'id');
        $before = $this->stock(1, 1);

        $this->assertStringEndsWith('/purchase-orders/' . $id, $client->post('/purchase-orders/' . $id . '/order')->location);
        $this->assertSame('Ordered', $this->value('SELECT status FROM purchase_orders WHERE id = ?', [$id]));
        $this->assertSame(200, $client->get('/purchase-orders/' . $id . '/receive')->status);

        $client->post('/purchase-orders/' . $id . '/receive', ['items' => [$itemIds[0] => '4', $itemIds[1] => '0', 99999 => '3']]);
        $this->assertSame('PartiallyReceived', $this->value('SELECT status FROM purchase_orders WHERE id = ?', [$id]));
        $this->assertSame($before + 4, $this->stock(1, 1));

        $client->post('/purchase-orders/' . $id . '/receive', ['items' => [$itemIds[0] => '50', $itemIds[1] => '5']]);
        $this->assertSame('Received', $this->value('SELECT status FROM purchase_orders WHERE id = ?', [$id]));
        $this->assertSame($before + 10, $this->stock(1, 1));
        $this->assertSame(2, (int) $this->value("SELECT COUNT(*) FROM stock_ledger WHERE reference_type = 'purchase_order' AND reference_id = ? AND product_id = 1", [$id]));

        $again = $client->post('/purchase-orders/' . $id . '/receive', ['items' => [$itemIds[0] => '1']]);
        $this->assertStringEndsWith('/purchase-orders/' . $id . '/receive', $again->location);
        $this->assertStringContainsString('alert-error', $client->get('/purchase-orders/' . $id . '/receive')->body);
        $this->assertSame($before + 10, $this->stock(1, 1));
    }

    public function testReceivingOnDraftOrderIsRejected(): void
    {
        $client = $this->loginAs(self::ADMIN);
        $id = $this->createPo($client);
        $itemId = (int) $this->value('SELECT id FROM purchase_order_items WHERE purchase_order_id = ?', [$id]);

        $response = $client->post('/purchase-orders/' . $id . '/receive', ['items' => [$itemId => '1']]);

        $this->assertStringEndsWith('/receive', $response->location);
        $this->assertSame('Draft', $this->value('SELECT status FROM purchase_orders WHERE id = ?', [$id]));
    }

    public function testCancelAndInvalidTransitionsAreHandled(): void
    {
        $client = $this->loginAs(self::ADMIN);
        $id = $this->createPo($client);

        $client->post('/purchase-orders/' . $id . '/cancel');
        $this->assertSame('Cancelled', $this->value('SELECT status FROM purchase_orders WHERE id = ?', [$id]));

        $again = $client->post('/purchase-orders/' . $id . '/order');
        $this->assertStringEndsWith('/purchase-orders/' . $id, $again->location);
        $this->assertStringContainsString('alert-error', $client->get('/purchase-orders/' . $id)->body);

        $received = $client->post('/purchase-orders/3/cancel');
        $this->assertSame(302, $received->status);
        $this->assertSame('Received', $this->value('SELECT status FROM purchase_orders WHERE id = 3'));

        $this->assertSame(302, $client->post('/purchase-orders/99999/order')->status);
        $this->assertSame(302, $client->post('/purchase-orders/99999/cancel')->status);
        $this->assertSame(302, $client->post('/purchase-orders/99999/receive', ['items' => [1 => '1']])->status);
    }

    public function testSalesRoleCannotWritePurchaseOrders(): void
    {
        $client = $this->loginAs(self::SALES);

        $this->assertSame(403, $client->get('/purchase-orders/create')->status);
        $this->assertSame(403, $client->post('/purchase-orders', $this->newPo())->status);
        $this->assertSame(403, $client->post('/purchase-orders/5/order')->status);
        $this->assertSame(403, $client->post('/purchase-orders/5/cancel')->status);
        $this->assertSame(403, $client->get('/purchase-orders/1/receive')->status);
        $this->assertSame(403, $client->post('/purchase-orders/1/receive')->status);
    }
}
