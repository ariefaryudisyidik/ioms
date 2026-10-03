<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use PDO;

final class MySqlPurchaseOrderRepository extends AbstractMySqlRepository implements PurchaseOrderRepositoryInterface
{
    private const RULES = [
        [self::COL_STATUS, 'status = ?', 'string'],
        ['supplier_id', 'supplier_id = ?', 'int'],
        [self::COL_WAREHOUSE_ID, 'warehouse_id = ?', 'int'],
        [self::COL_DATE_FROM, 'order_date >= ?', 'string'],
        [self::COL_DATE_TO, 'order_date <= ?', 'string'],
        ['search', '(po.po_number LIKE ? OR s.name LIKE ?)', 'like'],
    ];
    private const SEARCH_FROM = 'purchase_orders po LEFT JOIN suppliers s ON s.id = po.supplier_id';

    public function findById(int $id): ?PurchaseOrder
    {
        $row = $this->fetchRow(
            'SELECT id, po_number, supplier_id, warehouse_id, status, order_date, created_by
             FROM purchase_orders WHERE id = ?',
            [$id]
        );

        return $row ? PurchaseOrder::fromRow($row) : null;
    }

    public function findWithItems(int $id): ?PurchaseOrder
    {
        $po = $this->findById($id);
        if ($po !== null) {
            $po->items = $this->itemsFor($id);
        }

        return $po;
    }

    public function poNumberExists(string $poNumber): bool
    {
        return $this->valueExists('purchase_orders', 'po_number', $poNumber);
    }

    public function invalidReferences(int $supplierId, int $warehouseId, array $productIds): array
    {
        $checks = [
            'supplier_id' => ['suppliers', $supplierId, 'Supplier not found or inactive.'],
            'warehouse_id' => ['warehouses', $warehouseId, 'Warehouse not found or inactive.'],
        ];
        foreach ($productIds as $index => $productId) {
            $checks["items.$index"] = ['products', $productId, 'Product not found or inactive.'];
        }

        return $this->failedReferenceChecks($checks);
    }

    public function search(array $filters = []): array
    {
        $rows = $this->searchRows(
            'SELECT po.id, po.po_number, po.supplier_id, po.warehouse_id, po.status, po.order_date, po.created_by,
                    s.name AS supplier_name
             FROM ' . self::SEARCH_FROM,
            $filters,
            self::RULES,
            $this->dateOrder($filters, 'po.'),
            10
        );

        return array_map(fn ($r) => PurchaseOrder::fromRow($r), $rows);
    }

    public function countSearch(array $filters = []): int
    {
        return $this->countRows(self::SEARCH_FROM, $filters, self::RULES);
    }

    public function countsByStatus(): array
    {
        return $this->countsByStatusFor('purchase_orders');
    }

    public function save(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->id === null) {
            $po->id = $this->insert(
                'INSERT INTO purchase_orders (po_number, supplier_id, warehouse_id, status, order_date, created_by)
                 VALUES (?,?,?,?,?,?)',
                [$po->poNumber, $po->supplierId, $po->warehouseId, $po->status, $po->orderDate, $po->createdBy]
            );

            return $po;
        }

        $this->execute(
            'UPDATE purchase_orders SET po_number=?, supplier_id=?, warehouse_id=?, status=?, order_date=? WHERE id=?',
            [$po->poNumber, $po->supplierId, $po->warehouseId, $po->status, $po->orderDate, $po->id]
        );

        return $po;
    }

    public function saveItem(PurchaseOrderItem $item): PurchaseOrderItem
    {
        if ($item->id === null) {
            $item->id = $this->insert(
                'INSERT INTO purchase_order_items (purchase_order_id, product_id, qty_ordered, qty_received,
                    purchase_price)
                 VALUES (?,?,?,?,?)',
                [$item->purchaseOrderId, $item->productId, $item->qtyOrdered, $item->qtyReceived, $item->purchasePrice]
            );

            return $item;
        }

        $this->execute(
            'UPDATE purchase_order_items SET product_id=?, qty_ordered=?, qty_received=?, purchase_price=? WHERE id=?',
            [$item->productId, $item->qtyOrdered, $item->qtyReceived, $item->purchasePrice, $item->id]
        );

        return $item;
    }

    public function itemsFor(int $poId): array
    {
        $rows = $this->fetchRows(
            'SELECT id, purchase_order_id, product_id, qty_ordered, qty_received, purchase_price
             FROM purchase_order_items WHERE purchase_order_id = ?',
            [$poId]
        );

        return array_map(fn ($r) => PurchaseOrderItem::fromRow($r), $rows);
    }

    public function updateItemReceived(int $itemId, int $qtyReceived, ?PDO $pdo = null): void
    {
        $this->execute('UPDATE purchase_order_items SET qty_received = ? WHERE id = ?', [$qtyReceived, $itemId], $pdo);
    }

    public function updateStatus(int $poId, string $status, ?PDO $pdo = null): void
    {
        $this->execute('UPDATE purchase_orders SET status = ? WHERE id = ?', [$status, $poId], $pdo);
    }
}
