<?php
/** @var \App\Entity\Supplier[] $suppliers */
$pageTitle = 'Suppliers';
$role = $auth_user['role'] ?? '';
include __DIR__ . '/../partials/header.php';
?>
<div class="page-head">
    <h1>Suppliers</h1>
    <?php if ($role === 'Admin'): ?><a class="btn" href="/suppliers/create">+ New Supplier</a><?php endif; ?>
</div>
<?php if (empty($suppliers)): ?>
    <div class="empty-state"><div class="empty-icon">&#128666;</div><p>Belum ada data supplier.</p></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Name</th><th>Contact</th><th>Address</th><th>Status</th><?php if ($role === 'Admin'): ?><th>Actions</th><?php endif; ?></tr></thead>
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
                            <a class="btn btn-secondary btn-sm" href="/suppliers/<?= (int) $s->id ?>/edit">Edit</a>
                            <?php if ($s->isActive): ?>
                            <form method="post" action="/suppliers/<?= (int) $s->id ?>/deactivate" data-confirm="Deactivate this supplier?">
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
