<?php
/**
 * Index page shared by purchase and sales orders.
 *
 * @var string $title
 * @var string $baseUrl
 * @var string $createLabel
 * @var list<string> $createRoles
 * @var string $role
 * @var list<string> $statuses
 * @var array<string,mixed> $filters
 * @var array{id:string,label:string,options:list<array{0:int,1:string}>}|null $extraFilter
 * @var string $emptyTitle
 * @var string $emptyText
 * @var list<string> $headers
 * @var list<list<string>> $rows pre-escaped cell markup
 * @var int $page
 * @var int $total
 */
$perPage = 10;
$lastPage = (int) max(1, ceil($total / $perPage));
$sortValue = $filters['sort'] ?? 'date_desc';
$sortColumns = [
    'PO Number' => 'number', 'SO Number' => 'number', 'Supplier' => 'party', 'Customer' => 'party',
    'Order Date' => 'date', 'Status' => 'status',
];
$sortQuery = ['search' => $filters['search'] ?? '', 'status' => $filters['status'] ?? ''];
if ($extraFilter !== null) {
    $sortQuery[$extraFilter['id']] = $filters[$extraFilter['id']] ?? '';
}
$filterActive = $sortQuery['search'] !== '' || $sortQuery['status'] !== ''
    || ($extraFilter !== null && ($sortQuery[$extraFilter['id']] ?? '') !== '');
$buildUrl = static function (array $overrides) use ($filters, $extraFilter, $baseUrl, $sortValue) {
    $params = ['search' => $filters['search'] ?? '', 'status' => $filters['status'] ?? ''];
    if ($extraFilter !== null) {
        $params[$extraFilter['id']] = $filters[$extraFilter['id']] ?? '';
    }
    $params = array_merge($params + ['sort' => $sortValue], $overrides);
    $params = array_filter($params, static fn ($v) => $v !== '' && $v !== null);

    return $baseUrl . '?' . http_build_query($params);
};
?>
<div class="page-head">
    <h1><?= e($title) ?></h1>
    <?php if (in_array($role, $createRoles, true)): ?>
        <a class="btn" href="<?= e($baseUrl) ?>/create"><?= icon('plus') ?><?= e(ltrim(ltrim($createLabel, '+'))) ?></a>
    <?php endif; ?>
</div>

<form class="filter-bar" method="get" action="<?= e($baseUrl) ?>">
    <?php partial('search-field', ['placeholder' => 'Order number or name', 'value' => (string) ($filters['search'] ?? '')]); ?>
    <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?= e($s) ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= e(statusLabel($s)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($extraFilter !== null): ?>
        <div class="field">
            <label for="<?= e($extraFilter['id']) ?>"><?= e($extraFilter['label']) ?></label>
            <select id="<?= e($extraFilter['id']) ?>" name="<?= e($extraFilter['id']) ?>" class="js-combobox">
                <option value="">All</option>
                <?php foreach ($extraFilter['options'] as [$optId, $optName]): ?>
                    <option value="<?= (int) $optId ?>" <?= (int) ($filters[$extraFilter['id']] ?? 0) === (int) $optId ? 'selected' : '' ?>><?= e($optName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <input type="hidden" name="sort" value="<?= e($sortValue) ?>">
    <?php partial('filter-actions', ['baseUrl' => $baseUrl, 'active' => $filterActive]); ?>
</form>

<?php if ($rows === []): ?>
    <?php if ($filterActive): ?>
        <?php partial('empty-state', ['icon' => 'search', 'title' => 'No results found', 'text' => 'No orders match your filters.']); ?>
    <?php else: ?>
        <?php partial('empty-state', ['icon' => 'clipboard-list', 'title' => $emptyTitle, 'text' => $emptyText, 'action' => in_array($role, $createRoles, true) ? ['href' => $baseUrl . '/create', 'label' => ltrim(ltrim($createLabel, '+')), 'icon' => 'plus'] : null]); ?>
    <?php endif; ?>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr>
                <?php foreach ($headers as $h): ?>
                    <?php if (isset($sortColumns[$h])): ?>
                        <?php partial('sort-th', ['label' => $h, 'column' => $sortColumns[$h], 'sort' => $sortValue, 'baseUrl' => $baseUrl, 'query' => $sortQuery]); ?>
                    <?php else: ?>
                        <th><?= e($h) ?></th>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $cells): ?>
                <tr>
                    <?php foreach ($cells as $cell): ?>
                        <td><?= $cell ?></td>
                    <?php endforeach; ?>
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
