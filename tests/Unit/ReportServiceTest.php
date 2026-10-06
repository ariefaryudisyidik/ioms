<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repository\ReportRepositoryInterface;
use App\Service\ReportService;
use PHPUnit\Framework\TestCase;

final class ReportServiceTest extends TestCase
{
    public function testStockLedgerCsvShowsNamesAndOrderNumbersInsteadOfIds(): void
    {
        $repo = $this->createMock(ReportRepositoryInterface::class);
        $repo->method('stockLedgerRows')->willReturn([[
            'created_at' => '2026-10-06 08:15:23', 'sku' => 'SKU-0010', 'product_name' => 'Amplop Coklat F4',
            'warehouse_name' => 'Gudang Pusat Jakarta', 'movement_type' => 'Receipt', 'quantity' => 2,
            'reference_type' => 'purchase_order', 'reference_number' => 'PO-2026-0015', 'performed_by' => 'Rudi Hartono',
        ]]);

        $csv = (new ReportService($repo))->stockLedgerCsv(null, null);

        $this->assertStringContainsString('"Reference Number"', $csv);
        $this->assertStringContainsString('"Amplop Coklat F4"', $csv);
        $this->assertStringContainsString('"Purchase Order",PO-2026-0015,"Rudi Hartono"', $csv);
        $this->assertStringNotContainsString('Product ID', $csv);
    }

    public function testPurchaseStatusIsSpacedAndSalesExportPassesTheOwnerFilter(): void
    {
        $repo = $this->createMock(ReportRepositoryInterface::class);
        $repo->method('purchaseOrderRows')->willReturn([[
            'po_number' => 'PO-1', 'supplier_name' => 'CV Jaya', 'warehouse_name' => 'Pusat',
            'status' => 'PartiallyReceived',
            'order_date' => '2026-10-06', 'qty_ordered' => 10, 'qty_received' => 4, 'total_value' => '100.00',
            'created_by' => 'Rudi',
        ]]);
        $repo->expects($this->once())->method('salesOrderRows')
            ->with($this->callback(static fn (array $f) => $f['created_by'] === 7))
            ->willReturn([]);

        $service = new ReportService($repo);

        $this->assertStringContainsString('"Partially Received"', $service->orderStatusCsv('purchase', null, null));
        $this->assertStringContainsString('SO Number', $service->orderStatusCsv('sales', null, null, 7));
    }
}
