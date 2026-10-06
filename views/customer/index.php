<?php
/** @var \App\Entity\Customer[] $customers */
$pageTitle = 'Customers';
$role = $auth_user['role'] ?? '';
$canEdit = $role === 'Admin';
include __DIR__ . '/../partials/header.php';
?>
<div class="page-head">
    <h1>Customers</h1>
    <?php if ($canEdit): ?><a class="btn" href="/customers/create"><?= icon('plus') ?>New Customer</a><?php endif; ?>
</div>
<?php
$filters ??= ['search' => '', 'status' => '', 'sort' => ''];
$baseUrl = '/customers';
$sort = $filters['sort'] ?: 'name_asc';
$query = ['search' => $filters['search'] ?? '', 'status' => $filters['status'] ?? ''];
$filtered = $query['search'] !== '' || $query['status'] !== '';
partial('master-filter', ['baseUrl' => $baseUrl, 'placeholder' => 'Name, contact or address', 'withStatus' => true, 'filters' => $filters]);
?>
<?php if (empty($customers)): ?>
    <?php if ($filtered): ?>
        <?php partial('empty-state', ['icon' => 'search', 'title' => 'No results found', 'text' => 'No customers match your filters.']); ?>
    <?php else: ?>
        <?php partial('empty-state', ['icon' => 'contact', 'title' => 'No customers yet', 'text' => 'Add a customer to start creating sales orders.', 'action' => $canEdit ? ['href' => '/customers/create', 'label' => 'New Customer', 'icon' => 'plus'] : null]); ?>
    <?php endif; ?>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr>
                <?php foreach (['name' => 'Name', 'contact' => 'Contact', 'address' => 'Address', 'status' => 'Status'] as $column => $label) { partial('sort-th', ['label' => $label, 'column' => $column, 'sort' => $sort, 'baseUrl' => $baseUrl, 'query' => $query]); } ?>
                <?php if ($canEdit): ?><th>Actions</th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
                <tr>
                    <td><?= e($c->name) ?></td>
                    <td><?= e($c->contact ?? '-') ?></td>
                    <td class="wrap"><?= e($c->address ?? '-') ?></td>
                    <td><span class="badge badge-<?= $c->isActive ? 'active' : 'inactive' ?>"><?= $c->isActive ? 'Active' : 'Inactive' ?></span></td>
                    <?php if ($canEdit): ?>
                    <td>
                        <div class="btn-row">
                            <a class="btn btn-secondary btn-sm" href="/customers/<?= (int) $c->id ?>/edit"><?= icon('pencil') ?>Edit</a>
                            <?php if ($role === 'Admin' && $c->isActive): ?>
                            <form method="post" action="/customers/<?= (int) $c->id ?>/deactivate" data-confirm="Deactivate this customer?">
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
