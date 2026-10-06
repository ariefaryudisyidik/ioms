<?php
/**
 * @var bool $active
 * @var string $cancelHref
 */
?>
<?php partial('switch-field', ['active' => $active]); ?>
<div class="btn-row">
    <button type="submit" class="btn"><?= icon('save') ?>Save</button>
    <a class="btn btn-secondary" href="<?= e($cancelHref) ?>"><?= icon('x') ?>Cancel</a>
</div>
