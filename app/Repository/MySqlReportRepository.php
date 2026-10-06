<?php

declare(strict_types=1);

namespace App\Repository;

final class MySqlReportRepository extends AbstractMySqlRepository implements ReportRepositoryInterface
{
    public function stockLedgerRows(array $filters): array
    {
        $where = [];
        $params = [];
        if (!empty($filters[self::COL_DATE_FROM])) {
            $where[] = 'sl.created_at >= ?';
            $params[] = $filters[self::COL_DATE_FROM] . ' 00:00:00';
        }
        if (!empty($filters[self::COL_DATE_TO])) {
            $where[] = 'sl.created_at <= ?';
            $params[] = $filters[self::COL_DATE_TO] . ' 23:59:59';
        }

        return $this->fetchRows(
            "SELECT sl.created_at, p.sku, p.name AS product_name, w.name AS warehouse_name, sl.movement_type,
                    sl.quantity, sl.reference_type,
                    COALESCE(po.po_number, so.so_number) AS reference_number, u.name AS performed_by
             FROM stock_ledger sl
             LEFT JOIN products p ON p.id = sl.product_id
             LEFT JOIN warehouses w ON w.id = sl.warehouse_id
             LEFT JOIN users u ON u.id = sl.performed_by
             LEFT JOIN purchase_orders po ON sl.reference_type = 'purchase_order' AND po.id = sl.reference_id
             LEFT JOIN sales_orders so ON sl.reference_type = 'sales_order' AND so.id = sl.reference_id"
            . $this->whereClause($where) . ' ORDER BY sl.created_at DESC, sl.id DESC',
            $params
        );
    }

    public function purchaseOrderRows(array $filters): array
    {
        [$where, $params] = $this->orderDateFilter($filters, 'po');

        return $this->fetchRows(
            'SELECT po.po_number, s.name AS supplier_name, w.name AS warehouse_name, po.status, po.order_date,
                    u.name AS created_by,
                    COALESCE(t.qty_ordered, 0) AS qty_ordered, COALESCE(t.qty_received, 0) AS qty_received,
                    COALESCE(t.total_value, 0) AS total_value
             FROM purchase_orders po
             LEFT JOIN suppliers s ON s.id = po.supplier_id
             LEFT JOIN warehouses w ON w.id = po.warehouse_id
             LEFT JOIN users u ON u.id = po.created_by
             LEFT JOIN (
                 SELECT purchase_order_id, SUM(qty_ordered) AS qty_ordered, SUM(qty_received) AS qty_received,
                        SUM(qty_ordered * purchase_price) AS total_value
                 FROM purchase_order_items GROUP BY purchase_order_id
             ) t ON t.purchase_order_id = po.id'
            . $this->whereClause($where) . ' ORDER BY po.order_date DESC, po.id DESC',
            $params
        );
    }

    public function salesOrderRows(array $filters): array
    {
        [$where, $params] = $this->orderDateFilter($filters, 'so');
        if (!empty($filters['created_by'])) {
            $where[] = 'so.created_by = ?';
            $params[] = (int) $filters['created_by'];
        }

        return $this->fetchRows(
            'SELECT so.so_number, c.name AS customer_name, w.name AS warehouse_name, so.status, so.order_date,
                    u.name AS created_by, a.name AS approved_by,
                    COALESCE(t.qty, 0) AS qty, COALESCE(t.total_value, 0) AS total_value
             FROM sales_orders so
             LEFT JOIN customers c ON c.id = so.customer_id
             LEFT JOIN warehouses w ON w.id = so.warehouse_id
             LEFT JOIN users u ON u.id = so.created_by
             LEFT JOIN users a ON a.id = so.approved_by
             LEFT JOIN (
                 SELECT sales_order_id, SUM(qty) AS qty, SUM(qty * selling_price) AS total_value
                 FROM sales_order_items GROUP BY sales_order_id
             ) t ON t.sales_order_id = so.id'
            . $this->whereClause($where) . ' ORDER BY so.order_date DESC, so.id DESC',
            $params
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string[], 1: array<int, mixed>}
     */
    private function orderDateFilter(array $filters, string $alias): array
    {
        $where = [];
        $params = [];
        if (!empty($filters[self::COL_DATE_FROM])) {
            $where[] = "{$alias}.order_date >= ?";
            $params[] = $filters[self::COL_DATE_FROM];
        }
        if (!empty($filters[self::COL_DATE_TO])) {
            $where[] = "{$alias}.order_date <= ?";
            $params[] = $filters[self::COL_DATE_TO];
        }

        return [$where, $params];
    }

    /** @param string[] $conditions */
    private function whereClause(array $conditions): string
    {
        return $conditions === [] ? '' : self::SQL_WHERE . implode(' AND ', $conditions);
    }
}
