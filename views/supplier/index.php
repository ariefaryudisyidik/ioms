<?php
/** @var \App\Entity\Supplier[] $suppliers */
$pageTitle = 'Suppliers';
$role = $auth_user['role'] ?? '';
include __DIR__ . '/../partials/header.php';
?>
<div class="page-head">
    <h1>Suppliers</h1>
    <?php if ($role === 'Admin'): ?><a class="btn" href="/suppliers/create"><?= icon('plus') ?>New Supplier</a><?php endif; ?>
</div>
<?php
$filters ??= ['search' => '', 'status' => '', 'sort' => ''];
$baseUrl = '/suppliers';
$sort = $filters['sort'] ?: 'name_asc';
$query = ['search' => $filters['search'] ?? '', 'status' => $filters['status'] ?? ''];
$filtered = $query['search'] !== '' || $query['status'] !== '';
partial('master-filter', ['baseUrl' => $baseUrl, 'placeholder' => 'Name, contact or address', 'withStatus' => true, 'filters' => $filters]);
?>
<?php if (empty($suppliers)): ?>
    <?php if ($filtered): ?>
        <?php partial('empty-state', ['icon' => 'search', 'title' => 'No results found', 'text' => 'No suppliers match your filters.']); ?>
    <?php else: ?>
        <?php partial('empty-state', ['icon' => 'truck', 'title' => 'No suppliers yet', 'text' => 'Add a supplier to start creating purchase orders.', 'action' => $role === 'Admin' ? ['href' => '/suppliers/create', 'label' => 'New Supplier', 'icon' => 'plus'] : null]); ?>
    <?php endif; ?>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr>
                <?php foreach (['name' => 'Name', 'contact' => 'Contact', 'address' => 'Address', 'status' => 'Status'] as $column => $label) { partial('sort-th', ['label' => $label, 'column' => $column, 'sort' => $sort, 'baseUrl' => $baseUrl, 'query' => $query]); } ?>
                <?php if ($role === 'Admin'): ?><th>Actions</th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($suppliers as $s): ?>
                <tr>
                    <td><?= e($s->name) ?></td>
                    <td><?= e($s->contact ?? '-') ?></td>
                    <td class="wrap"><?= e($s->address ?? '-') ?></td>
                    <td><span class="badge badge-<?= $s->isActive ? 'active' : 'inactive' ?>"><?= $s->isActive ? 'Active' : 'Inactive' ?></span></td>
                    <?php if ($role === 'Admin'): ?>
                    <td>
                        <div class="btn-row">
                            <a class="btn btn-secondary btn-sm" href="/suppliers/<?= (int) $s->id ?>/edit"><?= icon('pencil') ?>Edit</a>
                            <?php if ($s->isActive): ?>
                            <form method="post" action="/suppliers/<?= (int) $s->id ?>/deactivate" data-confirm="Deactivate this supplier?">
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
