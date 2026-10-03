<?php
/** @var array<string,mixed> $summary */
/** @var array{id:int,name:string,email:string,role:string}|null $auth_user */
$pageTitle = 'Dashboard';
include __DIR__ . '/../partials/header.php';
$role = $auth_user['role'] ?? '';
?>
<div class="page-head">
    <h1>Dashboard</h1>
    <span class="text-muted">Welcome, <?= e($auth_user['name'] ?? '') ?> (<?= e($role) ?>)</span>
</div>

<?php
$card = static fn (string $label, string $value, string $class = ''): string =>
    '<div class="card' . $class . '"><div class="card-label">' . e($label) . '</div><div class="card-value">' . e($value) . '</div></div>';
$count = static fn (string $key): string => (string) (int) ($summary[$key] ?? 0);
$mine = $summary['my_so_counts'] ?? [];
[$danger, $warning, $info, $success] = [' card-danger', ' card-warning', ' card-info', ' card-success'];
?>
<div class="card-grid">
    <?php if ($role === 'Admin'): ?>
        <?= $card('Inventory Value', number_format((float) ($summary['inventory_value'] ?? 0), 2)) ?>
        <?= $card('Total Products', $count('total_products')) ?>
        <?= $card('Low Stock Products', $count('low_stock_count'), $danger) ?>
        <?= $card('SO Pending Approval', $count('pending_approval_count'), $warning) ?>
        <?= $card('SO Approved (to fulfill)', $count('so_approved_count'), $success) ?>
        <?= $card('PO Ordered', $count('po_ordered_count'), $info) ?>
        <?= $card('PO Partially Received', $count('po_partial_count')) ?>
    <?php elseif ($role === 'Sales'): ?>
        <?php foreach (['Draft' => '', 'PendingApproval' => $warning, 'Approved' => $info, 'Fulfilled' => $success, 'Cancelled' => ''] as $status => $class): ?>
            <?= $card('My orders: ' . $status, (string) (int) ($mine[$status] ?? 0), $class) ?>
        <?php endforeach; ?>
    <?php else: /* WarehouseStaff */ ?>
        <?= $card('Awaiting goods receipt (PO Ordered)', $count('po_ordered_count'), $info) ?>
        <?= $card('Awaiting goods receipt (PO Partially Received)', $count('po_partial_count'), $info) ?>
        <?= $card('Awaiting goods issue (SO Approved)', $count('so_approved_count'), $success) ?>
        <?= $card('Low Stock Products', $count('low_stock_count'), $danger) ?>
    <?php endif; ?>
</div>

<?php if ($role !== 'Sales'): ?>
<div class="panel">
    <h2>Low Stock Items</h2>
    <?php $lowStock = $summary['low_stock_items'] ?? []; ?>
    <?php if (empty($lowStock)): ?>
        <div class="empty-state">
            <div class="empty-icon">&#9989;</div>
            <p>Belum ada data. Semua produk berada di atas titik pemesanan ulang (reorder point).</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
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
        <a class="btn btn-secondary" href="/products">Products</a>
        <?php if (in_array($role, ['Admin', 'WarehouseStaff'], true)): ?>
            <a class="btn btn-secondary" href="/purchase-orders">Purchase Orders</a>
        <?php endif; ?>
        <a class="btn btn-secondary" href="/sales-orders">Sales Orders</a>
        <a class="btn btn-secondary" href="/reports">Reports</a>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
