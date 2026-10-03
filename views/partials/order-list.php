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
 * @var string|null $notice
 * @var string $emptyMessage
 * @var list<string> $headers
 * @var list<list<string>> $rows pre-escaped cell markup
 * @var int $page
 * @var int $total
 */
$perPage = 10;
$lastPage = (int) max(1, ceil($total / $perPage));
$sort = $filters['sort'] ?? 'desc';
$buildUrl = static function (array $overrides) use ($filters, $extraFilter, $baseUrl, $sort) {
    $params = ['search' => $filters['search'] ?? '', 'status' => $filters['status'] ?? ''];
    if ($extraFilter !== null) {
        $params[$extraFilter['id']] = $filters[$extraFilter['id']] ?? '';
    }
    $params = array_merge($params + ['sort' => $sort], $overrides);
    $params = array_filter($params, static fn ($v) => $v !== '' && $v !== null);

    return $baseUrl . '?' . http_build_query($params);
};
?>
<div class="page-head">
    <h1><?= e($title) ?></h1>
    <?php if (in_array($role, $createRoles, true)): ?>
        <a class="btn" href="<?= e($baseUrl) ?>/create"><?= e($createLabel) ?></a>
    <?php endif; ?>
</div>

<form class="filter-bar" method="get" action="<?= e($baseUrl) ?>">
    <div class="field">
        <label for="search">Search</label>
        <input type="search" id="search" name="search" maxlength="100" placeholder="Order number or name" value="<?= e((string) ($filters['search'] ?? '')) ?>">
    </div>
    <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?= e($s) ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($extraFilter !== null): ?>
        <div class="field">
            <label for="<?= e($extraFilter['id']) ?>"><?= e($extraFilter['label']) ?></label>
            <select id="<?= e($extraFilter['id']) ?>" name="<?= e($extraFilter['id']) ?>">
                <option value="">All</option>
                <?php foreach ($extraFilter['options'] as [$optId, $optName]): ?>
                    <option value="<?= (int) $optId ?>" <?= (int) ($filters[$extraFilter['id']] ?? 0) === (int) $optId ? 'selected' : '' ?>><?= e($optName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <div class="field">
        <label for="sort">Sort by Date</label>
        <select id="sort" name="sort">
            <option value="desc" <?= $sort === 'desc' ? 'selected' : '' ?>>Newest first</option>
            <option value="asc" <?= $sort === 'asc' ? 'selected' : '' ?>>Oldest first</option>
        </select>
    </div>
    <div class="field" style="min-width:auto;">
        <button type="submit" class="btn">Apply</button>
    </div>
</form>

<?php if ($notice !== null): ?>
    <p class="text-muted"><?= e($notice) ?></p>
<?php endif; ?>

<?php if ($rows === []): ?>
    <div class="empty-state"><div class="empty-icon">&#128203;</div><p><?= e($emptyMessage) ?></p></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><?php foreach ($headers as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
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
