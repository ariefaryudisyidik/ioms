<?php
/**
 * On/off switch for the is_active flag. Posts "1" when on and "0" when off (hidden input first).
 *
 * @var bool $active
 * @var string $hint
 */
$hint ??= 'Inactive records stay in the history but can no longer be used.';
?>
<div class="field switch-field">
    <input type="hidden" name="is_active" value="0">
    <label class="switch" for="is_active">
        <input type="checkbox" id="is_active" name="is_active" value="1" <?= $active ? 'checked' : '' ?>>
        <span class="switch-track" aria-hidden="true"></span>
        <span class="switch-text">
            <span class="switch-title">Active</span>
            <span class="switch-hint"><?= e($hint) ?></span>
        </span>
    </label>
</div>
