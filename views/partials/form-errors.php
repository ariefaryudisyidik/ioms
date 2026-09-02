<?php
/** @var array<string,string> $errors */
$errors = $errors ?? [];
?>
<?php if (!empty($errors)): ?>
    <div class="form-errors">
        <strong>Please fix the following:</strong>
        <ul>
            <?php foreach ($errors as $field => $message): ?>
                <li><?= e(is_string($message) ? $message : (string) $message) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
