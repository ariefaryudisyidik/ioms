<?php
/** @var \App\Entity\Product $product */
$pageTitle = $product->name;
$role = $auth_user['role'] ?? '';
$stocks = (new \App\Repository\MySqlProductStockRepository(\App\Core\Database::connection()))->findByProduct((int) $product->id);
$warehouses = new \App\Repository\MySqlWarehouseRepository(\App\Core\Database::connection());
include __DIR__ . '/../partials/header.php';
?>
<div class="page-head">
    <h1><?= e($product->name) ?></h1>
    <div class="btn-row">
        <?php if ($role === 'Admin'): ?>
            <a class="btn btn-secondary" href="/products/<?= (int) $product->id ?>/edit"><?= icon('pencil') ?>Edit</a>
        <?php endif; ?>
        <a class="btn btn-secondary" href="/products"><?= icon('arrow-left') ?>Back to list</a>
    </div>
</div>

<div class="panel">
    <?php if ($product->imagePath): ?>
        <img class="product-image" src="/uploads/<?= e($product->imagePath) ?>" alt="<?= e($product->name) ?>">
    <?php endif; ?>
    <dl class="detail-grid">
        <div><dt>SKU</dt><dd><?= e($product->sku) ?></dd></div>
        <div><dt>Unit</dt><dd><?= e($product->unit) ?></dd></div>
        <div><dt>Purchase Price</dt><dd><?= rupiah($product->purchasePrice) ?></dd></div>
        <div><dt>Selling Price</dt><dd><?= rupiah($product->sellingPrice) ?></dd></div>
        <div><dt>Reorder Point</dt><dd><?= (int) $product->reorderPoint ?></dd></div>
        <div><dt>Status</dt><dd><span class="badge badge-<?= $product->isActive ? 'active' : 'inactive' ?>"><?= $product->isActive ? 'Active' : 'Inactive' ?></span></dd></div>
    </dl>
</div>

<div class="panel">
    <h2>Stock per Warehouse</h2>
    <?php if (empty($stocks)): ?>
        <div class="empty-state"><div class="empty-icon"><?= icon('package') ?></div><p>Belum ada data stok untuk produk ini.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Warehouse</th><th>Quantity</th></tr></thead>
                <tbody>
                <?php $totalQuantity = 0; ?>
                <?php foreach ($stocks as $stock): ?>
                    <?php $wh = $warehouses->findById($stock->warehouseId); ?>
                    <?php $totalQuantity += (int) $stock->quantity; ?>
                    <tr>
                        <td><?= e($wh?->name ?? ('#' . $stock->warehouseId)) ?></td>
                        <td><?= (int) $stock->quantity ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row">Total</th>
                        <td><strong><?= $totalQuantity ?></strong><?= $totalQuantity < $product->reorderPoint ? ' <span class="badge badge-low">Low</span>' : '' ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
