<?php
/** @var \App\Entity\Warehouse[] $warehouses */
$pageTitle = 'Warehouses';
$role = $auth_user['role'] ?? '';
include __DIR__ . '/../partials/header.php';
?>
<div class="page-head">
    <h1>Warehouses</h1>
    <?php if ($role === 'Admin'): ?><a class="btn" href="/warehouses/create"><?= icon('plus') ?>New Warehouse</a><?php endif; ?>
</div>
<?php
$filters ??= ['search' => '', 'status' => '', 'sort' => ''];
$baseUrl = '/warehouses';
$sort = $filters['sort'] ?: 'name_asc';
$query = ['search' => $filters['search'] ?? '', 'status' => $filters['status'] ?? ''];
$filtered = $query['search'] !== '' || $query['status'] !== '';
partial('master-filter', ['baseUrl' => $baseUrl, 'placeholder' => 'Name or location', 'withStatus' => true, 'filters' => $filters]);
?>
<?php if (empty($warehouses)): ?>
    <?php if ($filtered): ?>
        <?php partial('empty-state', ['icon' => 'search', 'title' => 'No results found', 'text' => 'No warehouses match your filters.']); ?>
    <?php else: ?>
        <?php partial('empty-state', ['icon' => 'warehouse', 'title' => 'No warehouses yet', 'text' => 'Add a warehouse to start tracking stock by location.', 'action' => $role === 'Admin' ? ['href' => '/warehouses/create', 'label' => 'New Warehouse', 'icon' => 'plus'] : null]); ?>
    <?php endif; ?>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr>
                <?php foreach (['name' => 'Name', 'location' => 'Location', 'status' => 'Status'] as $column => $label) { partial('sort-th', ['label' => $label, 'column' => $column, 'sort' => $sort, 'baseUrl' => $baseUrl, 'query' => $query]); } ?>
                <?php if ($role === 'Admin'): ?><th>Actions</th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($warehouses as $w): ?>
                <tr>
                    <td><?= e($w->name) ?></td>
                    <td class="wrap"><?= e($w->location) ?></td>
                    <td><span class="badge badge-<?= $w->isActive ? 'active' : 'inactive' ?>"><?= $w->isActive ? 'Active' : 'Inactive' ?></span></td>
                    <?php if ($role === 'Admin'): ?>
                    <td>
                        <div class="btn-row">
                            <a class="btn btn-secondary btn-sm" href="/warehouses/<?= (int) $w->id ?>/edit"><?= icon('pencil') ?>Edit</a>
                            <?php if ($w->isActive): ?>
                            <form method="post" action="/warehouses/<?= (int) $w->id ?>/deactivate" data-confirm="Deactivate this warehouse?">
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
<?php endif; ?>
<?php include __DIR__ . '/../partials/footer.php'; ?>
