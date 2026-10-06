<?php
/**
 * Filter bar for the master data lists: search, optional status, apply and reset.
 *
 * @var string $baseUrl
 * @var string $placeholder
 * @var bool $withStatus
 * @var array{search?:string,status?:string,sort?:string} $filters
 */
$search = $filters['search'] ?? '';
$status = $filters['status'] ?? '';
?>
<form class="filter-bar" method="get" action="<?= e($baseUrl) ?>">
    <?php partial('search-field', ['placeholder' => $placeholder, 'value' => $search]); ?>
    <?php if ($withStatus): ?>
    <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
    </div>
    <?php endif; ?>
    <?php if (($filters['sort'] ?? '') !== ''): ?><input type="hidden" name="sort" value="<?= e($filters['sort']) ?>"><?php endif; ?>
    <?php partial('filter-actions', ['baseUrl' => $baseUrl, 'active' => $search !== '' || $status !== '']); ?>
</form>
