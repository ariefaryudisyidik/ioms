<?php
/**
 * @var string $id
 * @var string $label
 * @var string $type
 * @var string|null $value null omits the value attribute
 * @var string $attrs trusted literal attributes
 * @var bool $required
 * @var string|null $errorKey null disables error display
 * @var array<string,string> $errors
 */
$hasError = $errorKey !== null && isset($errors[$errorKey]);
?>
<div class="field<?= $hasError ? ' has-error' : '' ?>">
    <label for="<?= e($id) ?>"><?= e($label) ?></label>
    <input type="<?= e($type) ?>" id="<?= e($id) ?>" name="<?= e($id) ?>" <?= $attrs ?><?= $value !== null ? ' value="' . e($value) . '"' : '' ?><?= $required ? ' required' : '' ?>>
    <?php if ($hasError): ?><div class="field-error"><?= e($errors[$errorKey]) ?></div><?php endif; ?>
</div>
