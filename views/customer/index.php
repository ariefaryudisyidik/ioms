<?php
/** @var \App\Entity\Customer[] $customers */
$pageTitle = 'Customers';
$role = $auth_user['role'] ?? '';
$canEdit = in_array($role, ['Admin', 'Sales'], true);
include __DIR__ . '/../partials/header.php';
?>
<div class="page-head">
    <h1>Customers</h1>
    <?php if ($canEdit): ?><a class="btn" href="/customers/create">+ New Customer</a><?php endif; ?>
</div>
<?php if (empty($customers)): ?>
    <div class="empty-state"><div class="empty-icon">&#128100;</div><p>Belum ada data pelanggan.</p></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Name</th><th>Contact</th><th>Address</th><th>Status</th><?php if ($canEdit): ?><th>Actions</th><?php endif; ?></tr></thead>
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
                            <a class="btn btn-secondary btn-sm" href="/customers/<?= (int) $c->id ?>/edit">Edit</a>
                            <?php if ($role === 'Admin' && $c->isActive): ?>
                            <form method="post" action="/customers/<?= (int) $c->id ?>/deactivate" data-confirm="Deactivate this customer?">
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
<?php endif; ?>
<?php include __DIR__ . '/../partials/footer.php'; ?>
