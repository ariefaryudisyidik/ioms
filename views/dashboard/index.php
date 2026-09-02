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

<div class="card-grid">
    <?php if ($role === 'Admin'): ?>
        <div class="card"><div class="card-label">Total Products</div><div class="card-value"><?= (int) ($summary['total_products'] ?? 0) ?></div></div>
        <div class="card card-danger"><div class="card-label">Low Stock Products</div><div class="card-value"><?= (int) ($summary['low_stock_count'] ?? 0) ?></div></div>
        <div class="card card-warning"><div class="card-label">SO Pending Approval</div><div class="card-value"><?= (int) ($summary['pending_approval_count'] ?? 0) ?></div></div>
        <div class="card card-info"><div class="card-label">PO Ordered</div><div class="card-value"><?= (int) ($summary['po_ordered_count'] ?? 0) ?></div></div>
        <div class="card card-success"><div class="card-label">SO Fulfilled</div><div class="card-value"><?= (int) ($summary['so_fulfilled_count'] ?? 0) ?></div></div>
        <div class="card"><div class="card-label">PO Received</div><div class="card-value"><?= (int) ($summary['po_received_count'] ?? 0) ?></div></div>
    <?php elseif ($role === 'Sales'): ?>
        <div class="card"><div class="card-label">My Orders</div><div class="card-value"><?= (int) ($summary['my_orders_count'] ?? 0) ?></div></div>
        <div class="card card-warning"><div class="card-label">Pending Approval (All)</div><div class="card-value"><?= (int) ($summary['pending_approval_count'] ?? 0) ?></div></div>
        <div class="card card-success"><div class="card-label">Fulfilled (All)</div><div class="card-value"><?= (int) ($summary['so_fulfilled_count'] ?? 0) ?></div></div>
        <div class="card"><div class="card-label">Cancelled (All)</div><div class="card-value"><?= (int) ($summary['so_cancelled_count'] ?? 0) ?></div></div>
    <?php else: /* WarehouseStaff */ ?>
        <div class="card card-danger"><div class="card-label">Low Stock Products</div><div class="card-value"><?= (int) ($summary['low_stock_count'] ?? 0) ?></div></div>
        <div class="card card-info"><div class="card-label">PO Ordered (to receive)</div><div class="card-value"><?= (int) ($summary['po_ordered_count'] ?? 0) ?></div></div>
        <div class="card"><div class="card-label">PO Received</div><div class="card-value"><?= (int) ($summary['po_received_count'] ?? 0) ?></div></div>
        <div class="card card-success"><div class="card-label">SO Approved (to fulfill)</div><div class="card-value"><?= (int) ($summary['so_approved_count'] ?? 0) ?></div></div>
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
