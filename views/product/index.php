<?php
/** @var array{items:\App\Entity\Product[],total:int,page:int,per_page:int,last_page:int} $result */
/** @var array{search:string,category_id:?int,status:string,sort:string} $filters */
/** @var \App\Entity\Category[] $categories */
$pageTitle = 'Products';
$role = $auth_user['role'] ?? '';
$canManage = $role === 'Admin';
include __DIR__ . '/../partials/header.php';

$baseUrl = '/products';
$sort = ($filters['sort'] ?? '') !== '' ? $filters['sort'] : 'name_asc';
$sortQuery = [
    'search' => $filters['search'] ?? '',
    'category_id' => $filters['category_id'] ?? '',
    'status' => $filters['status'] ?? '',
];
$buildUrl = static function (array $overrides) use ($filters) {
    $params = array_merge([
        'search' => $filters['search'] ?? '',
        'category_id' => $filters['category_id'] ?? '',
        'status' => $filters['status'] ?? '',
        'sort' => $filters['sort'] ?? 'name_asc',
    ], $overrides);
    $params = array_filter($params, static fn ($v) => $v !== '' && $v !== null);

    return '/products?' . http_build_query($params);
};
?>
<div class="page-head">
    <h1>Products</h1>
    <?php if ($canManage): ?><a class="btn" href="/products/create"><?= icon('plus') ?>New Product</a><?php endif; ?>
</div>

<form class="filter-bar" method="get" action="/products">
    <?php partial('search-field', ['placeholder' => 'SKU or name', 'value' => (string) ($filters['search'] ?? '')]); ?>
    <div class="field">
        <label for="category_id">Category</label>
        <select id="category_id" name="category_id">
            <option value="">All</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int) $cat->id ?>" <?= ((int) ($filters['category_id'] ?? 0) === (int) $cat->id) ? 'selected' : '' ?>><?= e($cat->name) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="status">Stock Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <option value="low" <?= ($filters['status'] ?? '') === 'low' ? 'selected' : '' ?>>Low Stock</option>
            <option value="normal" <?= ($filters['status'] ?? '') === 'normal' ? 'selected' : '' ?>>Normal</option>
        </select>
    </div>
    <input type="hidden" name="sort" value="<?= e($sort) ?>">
    <?php partial('filter-actions', ['baseUrl' => $baseUrl, 'active' => ($filters['search'] ?? '') !== '' || ($filters['category_id'] ?? '') !== '' || ($filters['status'] ?? '') !== '']); ?>
</form>

<?php if (empty($result['items'])): ?>
    <?php if (($filters['search'] ?? '') !== '' || ($filters['category_id'] ?? '') !== '' || ($filters['status'] ?? '') !== ''): ?>
        <?php partial('empty-state', ['icon' => 'search', 'title' => 'No results found', 'text' => 'No products match your filters.']); ?>
    <?php else: ?>
        <?php partial('empty-state', ['icon' => 'package', 'title' => 'No products yet', 'text' => 'Add your first product to start tracking stock.', 'action' => $canManage ? ['href' => '/products/create', 'label' => 'New Product', 'icon' => 'plus'] : null]); ?>
    <?php endif; ?>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <?php foreach (['sku' => 'SKU', 'name' => 'Name', 'unit' => 'Unit', 'purchase_price' => 'Purchase Price', 'selling_price' => 'Selling Price', 'reorder_point' => 'Reorder Point', 'status' => 'Status'] as $column => $label) { partial('sort-th', ['label' => $label, 'column' => $column, 'sort' => $sort, 'baseUrl' => $baseUrl, 'query' => $sortQuery]); } ?>
                    <?php if ($canManage): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($result['items'] as $p): ?>
                <tr>
                    <td><?= e($p->sku) ?></td>
                    <td class="wrap"><a href="/products/<?= (int) $p->id ?>"><?= e($p->name) ?></a></td>
                    <td><?= e($p->unit) ?></td>
                    <td><?= rupiah($p->purchasePrice) ?></td>
                    <td><?= rupiah($p->sellingPrice) ?></td>
                    <td><?= (int) $p->reorderPoint ?></td>
                    <td><span class="badge badge-<?= $p->isActive ? 'active' : 'inactive' ?>"><?= $p->isActive ? 'Active' : 'Inactive' ?></span></td>
                    <?php if ($canManage): ?>
                    <td>
                        <div class="btn-row">
                            <a class="btn btn-secondary btn-sm" href="/products/<?= (int) $p->id ?>/edit"><?= icon('pencil') ?>Edit</a>
                            <?php if ($role === 'Admin' && $p->isActive): ?>
                            <form method="post" action="/products/<?= (int) $p->id ?>/delete" data-confirm="Deactivate this product?">
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="btn btn-danger btn-sm"><?= icon('ban') ?>Deactivate</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($result['last_page'] > 1): ?>
        <div class="pagination">
            <?php if ($result['page'] > 1): ?>
                <a href="<?= e($buildUrl(['page' => $result['page'] - 1])) ?>">&laquo; Prev</a>
            <?php else: ?>
                <span class="disabled">&laquo; Prev</span>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $result['last_page']; $i++): ?>
                <?php if ($i === $result['page']): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= e($buildUrl(['page' => $i])) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($result['page'] < $result['last_page']): ?>
                <a href="<?= e($buildUrl(['page' => $result['page'] + 1])) ?>">Next &raquo;</a>
            <?php else: ?>
                <span class="disabled">Next &raquo;</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php include __DIR__ . '/../partials/footer.php'; ?>
