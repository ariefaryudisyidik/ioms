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
        $pendingApprovalCount = $this->salesOrders->countByStatus(SalesOrder::STATUS_PENDING_APPROVAL);

        $summary = [
            'total_products' => $totalProducts,
            'low_stock_count' => $lowStockCount,
            'low_stock_items' => $this->stocks->lowStockList(),
            'pending_approval_count' => $pendingApprovalCount,
            'so_draft_count' => $this->salesOrders->countByStatus(SalesOrder::STATUS_DRAFT),
            'so_approved_count' => $this->salesOrders->countByStatus(SalesOrder::STATUS_APPROVED),
            'so_fulfilled_count' => $this->salesOrders->countByStatus(SalesOrder::STATUS_FULFILLED),
            'so_cancelled_count' => $this->salesOrders->countByStatus(SalesOrder::STATUS_CANCELLED),
            'po_draft_count' => $this->purchaseOrders->countSearch(['status' => PurchaseOrder::STATUS_DRAFT]),
            'po_ordered_count' => $this->purchaseOrders->countSearch(['status' => PurchaseOrder::STATUS_ORDERED]),
            'po_received_count' => $this->purchaseOrders->countSearch(['status' => PurchaseOrder::STATUS_RECEIVED]),
        ];

        if ($role === 'Sales') {
            $summary['my_orders_count'] = $this->salesOrders->countSearch(['created_by' => $userId]);
        }

        return $summary;
    }
}
