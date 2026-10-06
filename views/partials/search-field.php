<?php
/**
 * Search box with a leading icon and a clear button (public/assets/js/search-field.js shows it while there is text).
 *
 * @var string $placeholder
 * @var string $value
 */
$value ??= '';
?>
<div class="field field-search">
    <label for="search">Search</label>
    <div class="search-box">
        <?= icon('search') ?>
        <input type="search" id="search" name="search" maxlength="100" placeholder="<?= e($placeholder) ?>" value="<?= e($value) ?>" autocomplete="off">
        <button type="button" class="search-clear" aria-label="Clear search"<?= $value === '' ? ' hidden' : '' ?>><?= icon('x') ?></button>
    </div>
</div>
