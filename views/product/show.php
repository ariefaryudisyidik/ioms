<?php
/** @var \App\Entity\Product $product */
$pageTitle = $product->name;
$role = $auth_user['role'] ?? '';
$stocks = (new \App\Repository\MySqlProductStockRepository(\App\Core\Database::connection()))->findByProduct((int) $product->id);
$warehouses = new \App\Repository\MySqlWarehouseRepository(\App\Core\Database::connection());
$category = (new \App\Repository\MySqlCategoryRepository(\App\Core\Database::connection()))->findById((int) $product->categoryId);
$totalStock = array_sum(array_map(static fn ($stock) => (int) $stock->quantity, $stocks));
$isLow = $totalStock < $product->reorderPoint;
$margin = $product->sellingPrice - $product->purchasePrice;
$marginPercent = $product->purchasePrice > 0 ? round($margin / $product->purchasePrice * 100) : 0;
include __DIR__ . '/../partials/header.php';
?>
<div class="page-head">
    <h1><?= e($product->name) ?> <span class="badge badge-<?= $product->isActive ? 'active' : 'inactive' ?>"><?= $product->isActive ? 'Active' : 'Inactive' ?></span></h1>
    <div class="btn-row">
        <?php if ($role === 'Admin'): ?>
            <a class="btn btn-secondary" href="/products/<?= (int) $product->id ?>/edit"><?= icon('pencil') ?>Edit</a>
        <?php endif; ?>
        <a class="btn btn-secondary" href="/products"><?= icon('arrow-left') ?>Back to list</a>
    </div>
</div>

<div class="card-grid card-grid-3">
    <div class="card"><div class="card-label"><?= icon('boxes') ?><span>Total Stock</span></div><div class="card-value"><?= $totalStock ?> <?php if ($isLow): ?><span class="badge badge-low card-badge">Low</span><?php endif; ?></div></div>
    <div class="card"><div class="card-label"><?= icon('triangle-alert') ?><span>Reorder Point</span></div><div class="card-value"><?= (int) $product->reorderPoint ?></div></div>
    <div class="card"><div class="card-label"><?= icon('wallet') ?><span>Stock Value</span></div><div class="card-value"><?= rupiah($totalStock * $product->purchasePrice) ?></div></div>
</div>

<div class="detail-layout">
    <div class="panel">
        <h2>Product Information</h2>
        <?php if ($product->imagePath): ?>
            <img class="product-image" src="/uploads/<?= e($product->imagePath) ?>" alt="<?= e($product->name) ?>">
        <?php endif; ?>
        <dl class="info-list">
            <div><dt>SKU</dt><dd><?= e($product->sku) ?></dd></div>
            <div><dt>Category</dt><dd><?= e($category?->name ?? '-') ?></dd></div>
            <div><dt>Unit</dt><dd><?= e($product->unit) ?></dd></div>
            <div><dt>Status</dt><dd><span class="badge badge-<?= $product->isActive ? 'active' : 'inactive' ?>"><?= $product->isActive ? 'Active' : 'Inactive' ?></span></dd></div>
        </dl>
    </div>
    <div class="panel">
        <h2>Pricing</h2>
        <dl class="info-list">
            <div><dt>Purchase Price</dt><dd><?= rupiah($product->purchasePrice) ?></dd></div>
            <div><dt>Selling Price</dt><dd><?= rupiah($product->sellingPrice) ?></dd></div>
            <div><dt>Margin per Unit</dt><dd><?= rupiah($margin) ?> <span class="text-muted">(<?= $marginPercent ?>%)</span></dd></div>
        </dl>
    </div>
</div>

<div class="panel">
    <h2>Stock per Warehouse</h2>
    <?php if (empty($stocks)): ?>
        <?php partial('empty-state', ['compact' => true, 'icon' => 'package', 'title' => 'No stock recorded', 'text' => 'This product has no stock in any warehouse yet.']); ?>
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
