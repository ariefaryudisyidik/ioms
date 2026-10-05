<?php
/** @var \App\Entity\SalesOrder $so */
$pageTitle = 'SO ' . $so->soNumber;
$role = $auth_user['role'] ?? '';
$userId = $auth_user['id'] ?? null;
$pdo = \App\Core\Database::connection();
$customer = (new \App\Repository\MySqlCustomerRepository($pdo))->findById($so->customerId);
$warehouse = (new \App\Repository\MySqlWarehouseRepository($pdo))->findById($so->warehouseId);
$products = (new \App\Repository\MySqlProductRepository($pdo))->all();
$productsById = [];
foreach ($products as $p) { $productsById[$p->id] = $p; }

$canSubmit = $so->status === 'Draft' && $so->createdBy === $userId;
$canApprove = $role === 'Admin' && $so->status === 'PendingApproval' && $so->createdBy !== $userId;
$canReject = $role === 'Admin' && $so->status === 'PendingApproval';
$canFulfill = in_array($role, ['Admin', 'WarehouseStaff'], true) && $so->status === 'Approved';
$canCancel = in_array($role, ['Admin', 'Sales'], true) && !in_array($so->status, ['Fulfilled', 'Cancelled'], true);

include __DIR__ . '/../partials/header.php';
?>
<div class="page-head">
    <h1>Sales Order <?= e($so->soNumber) ?> <span class="badge badge-<?= strtolower($so->status) ?>"><?= e($so->status) ?></span></h1>
    <a class="btn btn-secondary" href="/sales-orders"><?= icon('arrow-left') ?>Back to list</a>
</div>

<?php if ($role !== 'Admin' && $so->status === 'PendingApproval'): ?>
    <div class="alert alert-warning alert-inline" role="note"><?= icon('triangle-alert') ?>Only an Admin can approve or reject this order.</div>
<?php endif; ?>

<div class="panel">
    <dl class="detail-grid">
        <div><dt>Customer</dt><dd><?= e($customer?->name ?? ('#' . $so->customerId)) ?></dd></div>
        <div><dt>Warehouse</dt><dd><?= e($warehouse?->name ?? ('#' . $so->warehouseId)) ?></dd></div>
        <div><dt>Order Date</dt><dd><?= e($so->orderDate) ?></dd></div>
    </dl>

    <div class="btn-row">
        <?php if ($canSubmit): ?>
            <form method="post" action="/sales-orders/<?= (int) $so->id ?>/submit">
                <button type="submit" class="btn"><?= icon('send') ?>Submit for Approval</button>
            </form>
        <?php endif; ?>
        <?php if ($canApprove): ?>
            <form method="post" action="/sales-orders/<?= (int) $so->id ?>/approve">
                <button type="submit" class="btn"><?= icon('check') ?>Approve</button>
            </form>
        <?php endif; ?>
        <?php if ($canReject): ?>
            <form method="post" action="/sales-orders/<?= (int) $so->id ?>/reject" data-confirm="Reject this sales order back to draft?">
                <button type="submit" class="btn btn-danger"><?= icon('circle-x') ?>Reject</button>
            </form>
        <?php endif; ?>
        <?php if ($canFulfill): ?>
            <form method="post" action="/sales-orders/<?= (int) $so->id ?>/fulfill" data-confirm="Fulfill this order and deduct stock?">
                <button type="submit" class="btn"><?= icon('truck') ?>Fulfill</button>
            </form>
        <?php endif; ?>
        <?php if ($canCancel): ?>
            <form method="post" action="/sales-orders/<?= (int) $so->id ?>/cancel" data-confirm="Cancel this sales order?">
                <button type="submit" class="btn btn-danger"><?= icon('x') ?>Cancel</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="panel">
    <h2>Items</h2>
    <?php if (empty($so->items)): ?>
        <div class="empty-state"><p>Belum ada item.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Product</th><th>Qty</th><th>Selling Price</th><th>Subtotal</th></tr></thead>
                <tbody>
                <?php $grandTotal = 0; ?>
                <?php foreach ($so->items as $item): ?>
                    <?php $product = $productsById[$item->productId] ?? null; $subtotal = $item->qty * $item->sellingPrice; $grandTotal += $subtotal; ?>
                    <tr>
                        <td><?= e($product?->name ?? ('#' . $item->productId)) ?></td>
                        <td><?= (int) $item->qty ?></td>
                        <td><?= number_format($item->sellingPrice, 2) ?></td>
                        <td><?= number_format($subtotal, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><th colspan="3" scope="row" class="text-right">Total</th><td><strong><?= number_format($grandTotal, 2) ?></strong></td></tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
