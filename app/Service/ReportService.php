<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\PurchaseOrderRepositoryInterface;
use App\Repository\SalesOrderRepositoryInterface;
use App\Repository\StockLedgerRepositoryInterface;

/**
 * Generates CSV exports for stock ledger movements and order status,
 * filtered by date range and (where relevant) by requester role.
 */
final class ReportService
{
    public function __construct(
        private StockLedgerRepositoryInterface $ledger,
        private SalesOrderRepositoryInterface $salesOrders,
        private PurchaseOrderRepositoryInterface $purchaseOrders,
    ) {
    }

    public function stockLedgerCsv(?string $dateFrom, ?string $dateTo): string
    {
        $entries = $this->ledger->search([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'limit' => 100000,
        ]);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['ID', 'Product ID', 'Warehouse ID', 'Movement Type', 'Quantity', 'Reference Type', 'Reference ID', 'Performed By', 'Created At']);
        foreach ($entries as $e) {
            fputcsv($handle, [$e->id, $e->productId, $e->warehouseId, $e->movementType, $e->quantity, $e->referenceType, $e->referenceId, $e->performedBy, $e->createdAt]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return (string) $csv;
    }

    /**
     * @param string $type "sales" or "purchase"
     */
    public function orderStatusCsv(string $type, ?string $dateFrom, ?string $dateTo, ?int $restrictToUserId = null): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($type === 'purchase') {
            fputcsv($handle, ['ID', 'PO Number', 'Supplier ID', 'Warehouse ID', 'Status', 'Order Date', 'Created By']);
            $orders = $this->purchaseOrders->search(['date_from' => $dateFrom, 'date_to' => $dateTo, 'limit' => 100000]);
            foreach ($orders as $o) {
                fputcsv($handle, [$o->id, $o->poNumber, $o->supplierId, $o->warehouseId, $o->status, $o->orderDate, $o->createdBy]);
            }
        } else {
            fputcsv($handle, ['ID', 'SO Number', 'Customer ID', 'Warehouse ID', 'Status', 'Order Date', 'Created By', 'Approved By']);
            $filters = ['date_from' => $dateFrom, 'date_to' => $dateTo, 'limit' => 100000];
            if ($restrictToUserId !== null) {
                $filters['created_by'] = $restrictToUserId;
            }
            $orders = $this->salesOrders->search($filters);
            foreach ($orders as $o) {
                fputcsv($handle, [$o->id, $o->soNumber, $o->customerId, $o->warehouseId, $o->status, $o->orderDate, $o->createdBy, $o->approvedBy]);
            }
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return (string) $csv;
    }
}
