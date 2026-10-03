<?php
/**
 * @var string $id
 * @var string $label
 * @var string $placeholder
 * @var string $selectClass
 * @var list<array{0:int,1:string}> $options [id, name] pairs
 * @var string $selected
 * @var array<string,string> $errors
 */
?>
<div class="field<?= isset($errors[$id]) ? ' has-error' : '' ?>">
    <label for="<?= e($id) ?>"><?= e($label) ?></label>
    <select id="<?= e($id) ?>" name="<?= e($id) ?>"<?= $selectClass !== '' ? ' class="' . e($selectClass) . '"' : '' ?> data-required required>
        <option value=""><?= e($placeholder) ?></option>
        <?php foreach ($options as [$optId, $optName]): ?>
            <option value="<?= (int) $optId ?>" <?= $selected === (string) $optId ? 'selected' : '' ?>><?= e($optName) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if (isset($errors[$id])): ?><div class="field-error"><?= e($errors[$id]) ?></div><?php endif; ?>
</div>
