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
<?php
$filters ??= ['search' => '', 'status' => '', 'sort' => ''];
$baseUrl = '/categories';
$sort = $filters['sort'] ?: 'name_asc';
$query = ['search' => $filters['search'] ?? '', 'status' => $filters['status'] ?? ''];
$filtered = $query['search'] !== '' || $query['status'] !== '';
partial('master-filter', ['baseUrl' => $baseUrl, 'placeholder' => 'Name or description', 'withStatus' => false, 'filters' => $filters]);
?>

<?php if (empty($categories)): ?>
    <?php if ($filtered): ?>
        <?php partial('empty-state', ['icon' => 'search', 'title' => 'No results found', 'text' => 'No categories match your filters.']); ?>
    <?php else: ?>
        <?php partial('empty-state', ['icon' => 'tags', 'title' => 'No categories yet', 'text' => 'Create your first category to organise products.', 'action' => $role === 'Admin' ? ['href' => '/categories/create', 'label' => 'New Category', 'icon' => 'plus'] : null]); ?>
    <?php endif; ?>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr>
                <?php foreach (['name' => 'Name', 'description' => 'Description'] as $column => $label) { partial('sort-th', ['label' => $label, 'column' => $column, 'sort' => $sort, 'baseUrl' => $baseUrl, 'query' => $query]); } ?>
                <?php if ($role === 'Admin'): ?><th>Actions</th><?php endif; ?>
            </tr></thead>
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
