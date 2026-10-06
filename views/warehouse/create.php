<?php
$pageTitle = 'New Warehouse';
$errors = $errors ?? [];
$old = ['name' => \App\Core\Session::old('name', ''), 'location' => \App\Core\Session::old('location', '')];
include __DIR__ . '/../partials/header.php';
?>
<h1>New Warehouse</h1>
<?php include __DIR__ . '/../partials/form-errors.php'; ?>
<div class="panel">
    <form method="post" action="/warehouses" data-validate novalidate>
        <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" data-required placeholder="Enter warehouse name" value="<?= e($old['name']) ?>" required>
            <?php if (isset($errors['name'])): ?><div class="field-error"><?= e($errors['name']) ?></div><?php endif; ?>
        </div>
        <div class="field">
            <label for="location">Location</label>
            <input type="text" id="location" name="location" placeholder="e.g. Jakarta" value="<?= e($old['location']) ?>">
        </div>
        <?php partial('switch-field', ['active' => true]); ?>
        <div class="btn-row">
            <button type="submit" class="btn"><?= icon('save') ?>Save</button>
            <a class="btn btn-secondary" href="/warehouses"><?= icon('x') ?>Cancel</a>
        </div>
    </form>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
