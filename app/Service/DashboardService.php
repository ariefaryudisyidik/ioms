<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PurchaseOrder;
use App\Entity\SalesOrder;
use App\Repository\ProductRepositoryInterface;
use App\Repository\ProductStockRepositoryInterface;
use App\Repository\PurchaseOrderRepositoryInterface;
use App\Repository\SalesOrderRepositoryInterface;

/**
 * Aggregates real, role-aware dashboard metrics (no hardcoded numbers).
 */
final class DashboardService
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private ProductStockRepositoryInterface $stocks,
        private SalesOrderRepositoryInterface $salesOrders,
        private PurchaseOrderRepositoryInterface $purchaseOrders,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function summaryFor(string $role, int $userId): array
    {
        $totalProducts = count($this->products->all(true));
        $lowStockCount = $this->stocks->countLowStock();
        $soCounts = $this->salesOrders->countsByStatus();
        $poCounts = $this->purchaseOrders->countsByStatus();

        $summary = [
            'total_products' => $totalProducts,
            'low_stock_count' => $lowStockCount,
            'low_stock_items' => $this->stocks->lowStockList(),
            'inventory_value' => $this->stocks->totalInventoryValue(),
            'pending_approval_count' => $soCounts[SalesOrder::STATUS_PENDING_APPROVAL] ?? 0,
            'so_draft_count' => $soCounts[SalesOrder::STATUS_DRAFT] ?? 0,
            'so_approved_count' => $soCounts[SalesOrder::STATUS_APPROVED] ?? 0,
            'so_fulfilled_count' => $soCounts[SalesOrder::STATUS_FULFILLED] ?? 0,
            'so_cancelled_count' => $soCounts[SalesOrder::STATUS_CANCELLED] ?? 0,
            'po_draft_count' => $poCounts[PurchaseOrder::STATUS_DRAFT] ?? 0,
            'po_ordered_count' => $poCounts[PurchaseOrder::STATUS_ORDERED] ?? 0,
            'po_partial_count' => $poCounts[PurchaseOrder::STATUS_PARTIALLY_RECEIVED] ?? 0,
            'po_received_count' => $poCounts[PurchaseOrder::STATUS_RECEIVED] ?? 0,
        ];

        if ($role === 'Sales') {
            $summary['my_so_counts'] = $this->salesOrders->countsByStatus($userId);
        }

        return $summary;
    }
}
