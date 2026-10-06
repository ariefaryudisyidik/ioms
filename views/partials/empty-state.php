<?php
/**
 * Centered empty state: icon, title, short explanation and an optional action.
 *
 * @var string $icon icon name from public/assets/icons
 * @var string $title
 * @var string $text
 * @var array{href:string,label:string,icon?:string,secondary?:bool}|null $action
 * @var bool $compact smaller variant for use inside a panel
 */
$action ??= null;
$compact ??= false;
?>
<div class="empty-state<?= $compact ? ' empty-state-compact' : '' ?>">
    <div class="empty-icon"><?= icon($icon) ?></div>
    <p class="empty-title"><?= e($title) ?></p>
    <p class="empty-text"><?= e($text) ?></p>
    <?php if ($action !== null): ?>
        <a class="btn<?= !empty($action['secondary']) ? ' btn-secondary' : '' ?>" href="<?= e($action['href']) ?>"><?= isset($action['icon']) ? icon($action['icon']) : '' ?><?= e($action['label']) ?></a>
    <?php endif; ?>
</div>
