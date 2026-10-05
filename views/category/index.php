<?php
/** @var \App\Entity\Category[] $categories */
$pageTitle = 'Categories';
$role = $auth_user['role'] ?? '';
include __DIR__ . '/../partials/header.php';
?>
<div class="page-head">
    <h1>Categories</h1>
    <?php if ($role === 'Admin'): ?>
        <a class="btn" href="/categories/create"><?= icon('plus') ?>New Category</a>
    <?php endif; ?>
</div>

<?php if (empty($categories)): ?>
    <div class="empty-state"><div class="empty-icon"><?= icon('tags') ?></div><p>Belum ada data kategori.</p></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Name</th><th>Description</th><?php if ($role === 'Admin'): ?><th>Actions</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($categories as $category): ?>
                <tr>
                    <td><?= e($category->name) ?></td>
                    <td class="wrap"><?= e($category->description ?? '-') ?></td>
                    <?php if ($role === 'Admin'): ?>
                    <td>
                        <div class="btn-row">
                            <a class="btn btn-secondary btn-sm" href="/categories/<?= (int) $category->id ?>/edit"><?= icon('pencil') ?>Edit</a>
                            <form method="post" action="/categories/<?= (int) $category->id ?>/delete" data-confirm="Delete this category?">
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="btn btn-danger btn-sm"><?= icon('trash-2') ?>Delete</button>
                            </form>
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
