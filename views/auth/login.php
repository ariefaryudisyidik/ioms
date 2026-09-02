<?php
/** @var array<string,string> $errors */
/** @var array{email:string} $old */
$errors = $errors ?? [];
$old = $old ?? ['email' => ''];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - IOMS</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <h1>IOMS</h1>
        <p class="subtitle">Inventory &amp; Order Management System</p>
        <?php include __DIR__ . '/../partials/flash.php'; ?>
        <?php include __DIR__ . '/../partials/form-errors.php'; ?>
        <form method="post" action="/login" data-validate novalidate>
            <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" data-required data-type="email"
                       value="<?= e($old['email'] ?? '') ?>" autocomplete="username" required>
                <?php if (isset($errors['email'])): ?><div class="field-error"><?= e($errors['email']) ?></div><?php endif; ?>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" data-required autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn">Log In</button>
        </form>
    </div>
</div>
<script src="/assets/js/api.js"></script>
<script src="/assets/js/validation.js"></script>
<script src="/assets/js/main.js"></script>
</body>
</html>
