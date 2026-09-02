<?php
/** @var \App\Entity\User[] $users */
$pageTitle = 'Users';
include __DIR__ . '/../partials/header.php';
?>
<div class="page-head">
    <h1>Users</h1>
    <a class="btn" href="/users/create">+ New User</a>
</div>
<?php if (empty($users)): ?>
    <div class="empty-state"><div class="empty-icon">&#128101;</div><p>Belum ada data pengguna.</p></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= e($u->name) ?></td>
                    <td><?= e($u->email) ?></td>
                    <td><?= e($u->role) ?></td>
                    <td><span class="badge badge-<?= $u->isActive ? 'active' : 'inactive' ?>"><?= $u->isActive ? 'Active' : 'Inactive' ?></span></td>
                    <td>
                        <div class="btn-row">
                            <a class="btn btn-secondary btn-sm" href="/users/<?= (int) $u->id ?>/edit">Edit</a>
                            <?php if ($u->isActive && $u->id !== ($auth_user['id'] ?? null)): ?>
                            <form method="post" action="/users/<?= (int) $u->id ?>/deactivate" data-confirm="Deactivate this user?">
                                <button type="submit" class="btn btn-danger btn-sm">Deactivate</button>
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
