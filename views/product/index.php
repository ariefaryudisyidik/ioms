<?php
/** @var array{items:\App\Entity\Product[],total:int,page:int,per_page:int,last_page:int} $result */
/** @var array{search:string,category_id:?int,status:string,sort:string} $filters */
/** @var \App\Entity\Category[] $categories */
$pageTitle = 'Products';
$role = $auth_user['role'] ?? '';
$canManage = in_array($role, ['Admin', 'WarehouseStaff'], true);
include __DIR__ . '/../partials/header.php';

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
    <?php if ($canManage): ?><a class="btn" href="/products/create">+ New Product</a><?php endif; ?>
</div>

<form class="filter-bar" method="get" action="/products">
    <div class="field">
        <label for="search">Search</label>
        <input type="text" id="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="SKU or name">
    </div>
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
    <div class="field">
        <label for="sort">Sort</label>
        <select id="sort" name="sort">
            <option value="name_asc" <?= ($filters['sort'] ?? '') === 'name_asc' ? 'selected' : '' ?>>Name A-Z</option>
            <option value="name_desc" <?= ($filters['sort'] ?? '') === 'name_desc' ? 'selected' : '' ?>>Name Z-A</option>
        </select>
    </div>
    <div class="field" style="min-width:auto;">
        <button type="submit" class="btn">Apply</button>
    </div>
</form>

<?php if (empty($result['items'])): ?>
    <div class="empty-state"><div class="empty-icon">&#128230;</div><p>Belum ada data produk yang cocok dengan filter ini.</p></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>SKU</th><th>Name</th><th>Unit</th><th>Purchase Price</th><th>Selling Price</th><th>Reorder Point</th><th>Status</th><?php if ($canManage): ?><th>Actions</th><?php endif; ?></tr>
            </thead>
            <tbody>
            <?php foreach ($result['items'] as $p): ?>
                <tr>
                    <td><?= e($p->sku) ?></td>
                    <td class="wrap"><a href="/products/<?= (int) $p->id ?>"><?= e($p->name) ?></a></td>
                    <td><?= e($p->unit) ?></td>
                    <td><?= number_format($p->purchasePrice, 2) ?></td>
                    <td><?= number_format($p->sellingPrice, 2) ?></td>
                    <td><?= (int) $p->reorderPoint ?></td>
                    <td><span class="badge badge-<?= $p->isActive ? 'active' : 'inactive' ?>"><?= $p->isActive ? 'Active' : 'Inactive' ?></span></td>
                    <?php if ($canManage): ?>
                    <td>
                        <div class="btn-row">
                            <a class="btn btn-secondary btn-sm" href="/products/<?= (int) $p->id ?>/edit">Edit</a>
                            <?php if ($role === 'Admin' && $p->isActive): ?>
                            <form method="post" action="/products/<?= (int) $p->id ?>/delete" data-confirm="Deactivate this product?">
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="btn btn-danger btn-sm">Deactivate</button>
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
