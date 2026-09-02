<?php
/** @var \App\Entity\Warehouse[] $warehouses */
$pageTitle = 'Warehouses';
$role = $auth_user['role'] ?? '';
include __DIR__ . '/../partials/header.php';
?>
<div class="page-head">
    <h1>Warehouses</h1>
    <?php if ($role === 'Admin'): ?><a class="btn" href="/warehouses/create">+ New Warehouse</a><?php endif; ?>
</div>
<?php if (empty($warehouses)): ?>
    <div class="empty-state"><div class="empty-icon">&#127970;</div><p>Belum ada data gudang.</p></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Name</th><th>Location</th><th>Status</th><?php if ($role === 'Admin'): ?><th>Actions</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($warehouses as $w): ?>
                <tr>
                    <td><?= e($w->name) ?></td>
                    <td class="wrap"><?= e($w->location) ?></td>
                    <td><span class="badge badge-<?= $w->isActive ? 'active' : 'inactive' ?>"><?= $w->isActive ? 'Active' : 'Inactive' ?></span></td>
                    <?php if ($role === 'Admin'): ?>
                    <td>
                        <div class="btn-row">
                            <a class="btn btn-secondary btn-sm" href="/warehouses/<?= (int) $w->id ?>/edit">Edit</a>
                            <?php if ($w->isActive): ?>
                            <form method="post" action="/warehouses/<?= (int) $w->id ?>/deactivate" data-confirm="Deactivate this warehouse?">
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
