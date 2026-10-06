<?php
/** @var \App\Entity\PurchaseOrder $po */
/** @var \App\Entity\Product[] $products */
$pageTitle = 'PO ' . $po->poNumber;
$role = $auth_user['role'] ?? '';
$pdo = \App\Core\Database::connection();
$supplier = (new \App\Repository\MySqlSupplierRepository($pdo))->findById($po->supplierId);
$warehouse = (new \App\Repository\MySqlWarehouseRepository($pdo))->findById($po->warehouseId);
$productsById = [];
foreach ($products as $p) { $productsById[$p->id] = $p; }
$canManage = in_array($role, ['Admin', 'WarehouseStaff'], true);
include __DIR__ . '/../partials/header.php';
?>
<div class="page-head">
    <h1>Purchase Order <?= e($po->poNumber) ?> <span class="badge badge-<?= strtolower($po->status) ?>"><?= e($po->status) ?></span></h1>
    <a class="btn btn-secondary" href="/purchase-orders"><?= icon('arrow-left') ?>Back to list</a>
</div>

<div class="panel">
    <dl class="detail-grid">
        <div><dt>Supplier</dt><dd><?= e($supplier?->name ?? ('#' . $po->supplierId)) ?></dd></div>
        <div><dt>Warehouse</dt><dd><?= e($warehouse?->name ?? ('#' . $po->warehouseId)) ?></dd></div>
        <div><dt>Order Date</dt><dd><?= e($po->orderDate) ?></dd></div>
    </dl>

    <?php if ($canManage): ?>
    <div class="btn-row">
        <?php if ($po->status === 'Draft'): ?>
            <form method="post" action="/purchase-orders/<?= (int) $po->id ?>/order">
                <button type="submit" class="btn"><?= icon('check-check') ?>Mark as Ordered</button>
            </form>
        <?php endif; ?>
        <?php if (in_array($po->status, ['Ordered', 'PartiallyReceived'], true)): ?>
            <a class="btn" href="/purchase-orders/<?= (int) $po->id ?>/receive"><?= icon('package-plus') ?>Receive Goods</a>
        <?php endif; ?>
        <?php if (!in_array($po->status, ['Received', 'Cancelled'], true)): ?>
            <form method="post" action="/purchase-orders/<?= (int) $po->id ?>/cancel" data-confirm="Cancel this purchase order?">
                <button type="submit" class="btn btn-danger"><?= icon('circle-x') ?>Cancel Order</button>
            </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="panel">
    <h2>Items</h2>
    <?php if (empty($po->items)): ?>
        <div class="empty-state"><p>Belum ada item.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Product</th><th>Ordered</th><th>Received</th><th>Remaining</th><th>Purchase Price</th></tr></thead>
                <tbody>
                <?php foreach ($po->items as $item): ?>
                    <?php $product = $productsById[$item->productId] ?? null; ?>
                    <tr>
                        <td><?= e($product?->name ?? ('#' . $item->productId)) ?></td>
                        <td><?= (int) $item->qtyOrdered ?></td>
                        <td><?= (int) $item->qtyReceived ?></td>
                        <td><?= (int) $item->remaining() ?></td>
                        <td><?= rupiah($item->purchasePrice) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
