<?php
/** @var array<string,mixed> $summary */
/** @var array{id:int,name:string,email:string,role:string}|null $auth_user */
$pageTitle = 'Dashboard';
include __DIR__ . '/../partials/header.php';
$role = $auth_user['role'] ?? '';
?>
<div class="page-head">
    <h1>Dashboard</h1>
</div>

<?php
$card = static fn (string $label, string $value, string $icon = 'package'): string =>
    '<div class="card"><div class="card-label">' . icon($icon) . '<span>' . e($label) . '</span></div><div class="card-value">' . e($value) . '</div></div>';
$count = static fn (string $key): string => (string) (int) ($summary[$key] ?? 0);
$mine = $summary['my_so_counts'] ?? [];
?>
<?php if ($role === 'Admin'): ?>
<?php $awaitingReceipt = (string) ((int) ($summary['po_ordered_count'] ?? 0) + (int) ($summary['po_partial_count'] ?? 0)); ?>
<div class="card-grid card-grid-3">
    <?= $card('Inventory Value', rupiah((float) ($summary['inventory_value'] ?? 0)), 'wallet') ?>
    <?= $card('Potential Revenue', rupiah((float) ($summary['potential_revenue'] ?? 0)), 'trending-up') ?>
    <?= $card('Potential Margin', rupiah((float) ($summary['potential_margin'] ?? 0)), 'chart-column') ?>
</div>
<?php endif; ?>
<div class="card-grid<?= $role === 'Admin' ? ' card-grid-3' : '' ?>">
    <?php if ($role === 'Admin'): ?>
        <?= $card('Low Stock Products', $count('low_stock_count'), 'triangle-alert') ?>
        <?= $card('SO Pending Approval', $count('pending_approval_count'), 'hourglass') ?>
        <?= $card('PO Awaiting Receipt', $awaitingReceipt, 'clipboard-list') ?>
    <?php elseif ($role === 'Sales'): ?>
        <?php foreach (['Draft', 'PendingApproval', 'Approved', 'Fulfilled', 'Cancelled'] as $status): ?>
            <?= $card(statusLabel($status), (string) (int) ($mine[$status] ?? 0), 'file-text') ?>
        <?php endforeach; ?>
    <?php else: /* WarehouseStaff */ ?>
        <?= $card('Awaiting goods receipt (PO Ordered)', $count('po_ordered_count'), 'clipboard-list') ?>
        <?= $card('Awaiting goods receipt (PO Partially Received)', $count('po_partial_count'), 'package-check') ?>
        <?= $card('Awaiting goods issue (SO Approved)', $count('so_approved_count'), 'truck') ?>
        <?= $card('Low Stock Products', $count('low_stock_count'), 'triangle-alert') ?>
    <?php endif; ?>
</div>

<?php if ($role === 'Sales'): ?>
<div class="panel">
    <h2>Recent Sales Orders</h2>
    <?php $recent = $summary['my_recent_orders'] ?? []; ?>
    <?php if (empty($recent)): ?>
        <?php partial('empty-state', ['compact' => true, 'icon' => 'shopping-cart', 'title' => 'No sales orders yet', 'text' => 'Orders you create will show up here.', 'action' => ['href' => '/sales-orders/create', 'label' => 'New Sales Order', 'icon' => 'plus']]); ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table table-compact">
                <thead>
                    <tr><th>SO Number</th><th>Customer</th><th class="hide-sm">Order Date</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $order): ?>
                        <tr>
                            <td><a href="/sales-orders/<?= (int) $order->id ?>"><?= e($order->soNumber) ?></a></td>
                            <td class="wrap"><?= e($order->customerName ?? '-') ?></td>
                            <td class="hide-sm"><?= e($order->orderDate) ?></td>
                            <td><span class="badge badge-<?= e(strtolower($order->status)) ?>"><?= e(statusLabel($order->status)) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($role !== 'Sales'): ?>
<div class="panel">
    <h2>Low Stock Items</h2>
    <?php $lowStock = $summary['low_stock_items'] ?? []; ?>
    <?php if (empty($lowStock)): ?>
        <?php partial('empty-state', ['compact' => true, 'icon' => 'shield-check', 'title' => 'Stock levels look healthy', 'text' => 'Every product is above its reorder point.']); ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table table-compact">
                <thead>
                    <tr><th>SKU</th><th>Name</th><th>Total Stock</th><th>Reorder Point</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($lowStock as $item): ?>
                        <tr>
                            <td><?= e($item['sku']) ?></td>
                            <td class="wrap"><a href="/products/<?= (int) $item['product_id'] ?>"><?= e($item['name']) ?></a></td>
                            <td><span class="badge badge-low"><?= (int) $item['total'] ?></span></td>
                            <td><?= (int) $item['reorder_point'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="panel">
    <h2>Quick Links</h2>
    <div class="btn-row">
        <a class="btn btn-secondary" href="/products"><?= icon('package') ?>Products</a>
        <?php if (in_array($role, ['Admin', 'WarehouseStaff'], true)): ?>
            <a class="btn btn-secondary" href="/purchase-orders"><?= icon('clipboard-list') ?>Purchase Orders</a>
        <?php endif; ?>
        <a class="btn btn-secondary" href="/sales-orders"><?= icon('shopping-cart') ?>Sales Orders</a>
        <a class="btn btn-secondary" href="/reports"><?= icon('chart-column') ?>Reports</a>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
