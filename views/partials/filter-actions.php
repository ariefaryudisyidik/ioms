<?php
/**
 * Apply and Reset buttons that close every filter bar, so all lists look and behave the same.
 *
 * @var string $baseUrl list URL; Reset links to it without any filter or sort
 * @var bool $active whether any filter is on; Reset is only shown then
 */
?>
<div class="field filter-actions">
    <button type="submit" class="btn"><?= icon('funnel') ?>Apply</button>
    <?php if ($active): ?>
        <a class="btn btn-secondary" href="<?= e($baseUrl) ?>"><?= icon('x') ?>Reset</a>
    <?php endif; ?>
</div>
