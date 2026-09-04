<?php
/** @var \App\Entity\SalesOrder[] $items */
/** @var int $total */
/** @var int $page */
/** @var array $filters */
$pageTitle = 'Sales Orders';
$role = $auth_user['role'] ?? '';
$perPage = 10;
$lastPage = (int) max(1, ceil($total / $perPage));
include __DIR__ . '/../partials/header.php';

$buildUrl = static function (array $overrides) use ($filters) {
    $params = array_merge([
        'status' => $filters['status'] ?? '',
        'sort' => $filters['sort'] ?? 'desc',
    ], $overrides);
    $params = array_filter($params, static fn ($v) => $v !== '' && $v !== null);

    return '/sales-orders?' . http_build_query($params);
};

$statuses = ['Draft', 'PendingApproval', 'Approved', 'Fulfilled', 'Cancelled'];
?>
<div class="page-head">
    <h1>Sales Orders</h1>
    <?php if (in_array($role, ['Admin', 'Sales'], true)): ?>
        <a class="btn" href="/sales-orders/create">+ New Sales Order</a>
    <?php endif; ?>
</div>

<form class="filter-bar" method="get" action="/sales-orders">
    <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?= e($s) ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="sort">Sort by Date</label>
        <select id="sort" name="sort">
            <option value="desc" <?= ($filters['sort'] ?? 'desc') === 'desc' ? 'selected' : '' ?>>Newest first</option>
            <option value="asc" <?= ($filters['sort'] ?? 'desc') === 'asc' ? 'selected' : '' ?>>Oldest first</option>
        </select>
    </div>
    <div class="field" style="min-width:auto;">
        <button type="submit" class="btn">Apply</button>
    </div>
</form>

<?php if ($role === 'Sales'): ?>
    <p class="text-muted">Showing only sales orders you created.</p>
<?php endif; ?>

<?php if (empty($items)): ?>
    <div class="empty-state"><div class="empty-icon">&#128203;</div><p>Belum ada data sales order.</p></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>SO Number</th><th>Order Date</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $so): ?>
                <tr>
                    <td><?= e($so->soNumber) ?></td>
                    <td><?= e($so->orderDate) ?></td>
                    <td><span class="badge badge-<?= strtolower($so->status) ?>"><?= e($so->status) ?></span></td>
                    <td><a class="btn btn-secondary btn-sm" href="/sales-orders/<?= (int) $so->id ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($lastPage > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?><a href="<?= e($buildUrl(['page' => $page - 1])) ?>">&laquo; Prev</a><?php else: ?><span class="disabled">&laquo; Prev</span><?php endif; ?>
            <?php for ($i = 1; $i <= $lastPage; $i++): ?>
                <?php if ($i === $page): ?><span class="current"><?= $i ?></span><?php else: ?><a href="<?= e($buildUrl(['page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $lastPage): ?><a href="<?= e($buildUrl(['page' => $page + 1])) ?>">Next &raquo;</a><?php else: ?><span class="disabled">Next &raquo;</span><?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php include __DIR__ . '/../partials/footer.php'; ?>
