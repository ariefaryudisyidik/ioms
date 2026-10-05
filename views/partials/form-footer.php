<?php
/**
 * @var bool $active
 * @var string $cancelHref
 */
?>
<div class="field checkbox-field">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" id="is_active" name="is_active" value="1" <?= $active ? 'checked' : '' ?>>
    <label for="is_active" style="margin:0;">Active</label>
</div>
<div class="btn-row">
    <button type="submit" class="btn"><?= icon('save') ?>Save</button>
    <a class="btn btn-secondary" href="<?= e($cancelHref) ?>"><?= icon('x') ?>Cancel</a>
</div>
