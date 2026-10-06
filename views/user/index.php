<?php
/** @var \App\Entity\User[] $users */
$pageTitle = 'Users';
include __DIR__ . '/../partials/header.php';
?>
<div class="page-head">
    <h1>Users</h1>
    <a class="btn" href="/users/create"><?= icon('plus') ?>New User</a>
</div>
<?php
$filters ??= ['search' => '', 'status' => '', 'sort' => ''];
$baseUrl = '/users';
$sort = $filters['sort'] ?: 'name_asc';
$query = ['search' => $filters['search'] ?? '', 'status' => $filters['status'] ?? ''];
$filtered = $query['search'] !== '' || $query['status'] !== '';
partial('master-filter', ['baseUrl' => $baseUrl, 'placeholder' => 'Name, email or role', 'withStatus' => true, 'filters' => $filters]);
?>
<?php if (empty($users)): ?>
    <?php partial('empty-state', ['icon' => 'search', 'title' => 'No results found', 'text' => 'No users match your filters.']); ?>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr>
                <?php foreach (['name' => 'Name', 'email' => 'Email', 'role' => 'Role', 'status' => 'Status'] as $column => $label) { partial('sort-th', ['label' => $label, 'column' => $column, 'sort' => $sort, 'baseUrl' => $baseUrl, 'query' => $query]); } ?>
                <th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= e($u->name) ?></td>
                    <td><?= e($u->email) ?></td>
                    <td><?= e(roleLabel($u->role)) ?></td>
                    <td><span class="badge badge-<?= $u->isActive ? 'active' : 'inactive' ?>"><?= $u->isActive ? 'Active' : 'Inactive' ?></span></td>
                    <td>
                        <div class="btn-row">
                            <a class="btn btn-secondary btn-sm" href="/users/<?= (int) $u->id ?>/edit"><?= icon('pencil') ?>Edit</a>
                            <?php if ($u->isActive && $u->id !== ($auth_user['id'] ?? null)): ?>
                            <form method="post" action="/users/<?= (int) $u->id ?>/deactivate" data-confirm="Deactivate this user?">
                                <button type="submit" class="btn btn-danger btn-sm"><?= icon('ban') ?>Deactivate</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php include __DIR__ . '/../partials/footer.php'; ?>
