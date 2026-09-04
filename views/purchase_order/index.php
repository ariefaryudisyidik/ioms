<?php
/** @var \App\Entity\PurchaseOrder[] $items */
/** @var int $total */
/** @var int $page */
/** @var array $filters */
/** @var \App\Entity\Supplier[] $suppliers */
$pageTitle = 'Purchase Orders';
$role = $auth_user['role'] ?? '';
$perPage = 10;
$lastPage = (int) max(1, ceil($total / $perPage));
include __DIR__ . '/../partials/header.php';

$buildUrl = static function (array $overrides) use ($filters) {
    $params = array_merge([
        'status' => $filters['status'] ?? '',
        'supplier_id' => $filters['supplier_id'] ?? '',
        'sort' => $filters['sort'] ?? 'desc',
    ], $overrides);
    $params = array_filter($params, static fn ($v) => $v !== '' && $v !== null);

    return '/purchase-orders?' . http_build_query($params);
};

$statuses = ['Draft', 'Ordered', 'PartiallyReceived', 'Received', 'Cancelled'];
?>
<div class="page-head">
    <h1>Purchase Orders</h1>
    <?php if (in_array($role, ['Admin', 'WarehouseStaff'], true)): ?>
        <a class="btn" href="/purchase-orders/create">+ New Purchase Order</a>
    <?php endif; ?>
</div>

<form class="filter-bar" method="get" action="/purchase-orders">
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
        <label for="supplier_id">Supplier</label>
        <select id="supplier_id" name="supplier_id">
            <option value="">All</option>
            <?php foreach ($suppliers as $sup): ?>
                <option value="<?= (int) $sup->id ?>" <?= (int) ($filters['supplier_id'] ?? 0) === (int) $sup->id ? 'selected' : '' ?>><?= e($sup->name) ?></option>
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

<?php if (empty($items)): ?>
    <div class="empty-state"><div class="empty-icon">&#128203;</div><p>Belum ada data purchase order.</p></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>PO Number</th><th>Supplier</th><th>Order Date</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $po): ?>
                <tr>
                    <td><?= e($po->poNumber) ?></td>
                    <td><?= e($po->supplierName ?? '-') ?></td>
                    <td><?= e($po->orderDate) ?></td>
                    <td><span class="badge badge-<?= strtolower($po->status) ?>"><?= e($po->status) ?></span></td>
                    <td><a class="btn btn-secondary btn-sm" href="/purchase-orders/<?= (int) $po->id ?>">View</a></td>
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
