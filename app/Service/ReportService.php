<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ReportRepositoryInterface;

/**
 * Generates CSV exports for stock ledger movements and order status,
 * filtered by date range and (where relevant) by requester role. Every
 * column is human-readable: names and order numbers instead of raw IDs.
 */
final class ReportService
{
    private const REFERENCE_LABELS = [
        'purchase_order' => 'Purchase Order',
        'sales_order' => 'Sales Order',
    ];

    public function __construct(private ReportRepositoryInterface $reports)
    {
    }

    public function stockLedgerCsv(?string $dateFrom, ?string $dateTo): string
    {
        $rows = $this->reports->stockLedgerRows(['date_from' => $dateFrom, 'date_to' => $dateTo]);

        return $this->toCsv(
            [
                'SKU', 'Product', 'Warehouse', 'Movement Type', 'Quantity',
                'Reference', 'Reference Number', 'Performed By', 'Date & Time',
            ],
            array_map(fn (array $r) => [
                $r['sku'],
                $r['product_name'],
                $r['warehouse_name'],
                $r['movement_type'],
                $r['quantity'],
                self::REFERENCE_LABELS[$r['reference_type']] ?? $r['reference_type'],
                $r['reference_number'],
                $r['performed_by'],
                $r['created_at'],
            ], $rows)
        );
    }

    /**
     * @param string $type "sales" or "purchase"
     */
    public function orderStatusCsv(
        string $type,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $restrictToUserId = null
    ): string {
        $filters = ['date_from' => $dateFrom, 'date_to' => $dateTo];

        if ($type === 'purchase') {
            return $this->toCsv(
                [
                    'PO Number', 'Supplier', 'Warehouse', 'Status', 'Order Date',
                    'Total Qty Ordered', 'Total Qty Received', 'Total Value', 'Created By',
                ],
                array_map(fn (array $r) => [
                    $r['po_number'],
                    $r['supplier_name'],
                    $r['warehouse_name'],
                    $this->statusLabel((string) $r['status']),
                    $r['order_date'],
                    $r['qty_ordered'],
                    $r['qty_received'],
                    $r['total_value'],
                    $r['created_by'],
                ], $this->reports->purchaseOrderRows($filters))
            );
        }

        if ($restrictToUserId !== null) {
            $filters['created_by'] = $restrictToUserId;
        }

        return $this->toCsv(
            [
                'SO Number', 'Customer', 'Warehouse', 'Status', 'Order Date',
                'Total Qty', 'Total Value', 'Created By', 'Approved By',
            ],
            array_map(fn (array $r) => [
                $r['so_number'],
                $r['customer_name'],
                $r['warehouse_name'],
                $this->statusLabel((string) $r['status']),
                $r['order_date'],
                $r['qty'],
                $r['total_value'],
                $r['created_by'],
                $r['approved_by'],
            ], $this->reports->salesOrderRows($filters))
        );
    }

    /** "PartiallyReceived" -> "Partially Received". */
    private function statusLabel(string $status): string
    {
        return trim((string) preg_replace('/(?<!^)(?=[A-Z])/', ' ', $status));
    }

    /**
     * @param string[] $header
     * @param array<int, array<int, mixed>> $rows
     */
    private function toCsv(array $header, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        $this->putRow($handle, $header);
        foreach ($rows as $row) {
            $this->putRow($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return (string) $csv;
    }

    /**
     * Writes one CSV row, neutralising spreadsheet formula injection: text
     * cells starting with = + - @ (or a tab/CR) get a leading apostrophe.
     *
     * @param resource $handle
     * @param array<int,mixed> $cells
     */
    private function putRow($handle, array $cells): void
    {
        fputcsv($handle, array_map(
            static fn ($cell) => is_string($cell) && preg_match('/^[=+\-@\t\r]/', $cell) === 1 ? "'" . $cell : $cell,
            $cells
        ), ',', '"', '');
    }
}
