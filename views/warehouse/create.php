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
            <input type="text" id="name" name="name" data-required value="<?= e($old['name']) ?>" required>
            <?php if (isset($errors['name'])): ?><div class="field-error"><?= e($errors['name']) ?></div><?php endif; ?>
        </div>
        <div class="field">
            <label for="location">Location</label>
            <input type="text" id="location" name="location" value="<?= e($old['location']) ?>">
        </div>
        <div class="field checkbox-field">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" id="is_active" name="is_active" value="1" checked>
            <label for="is_active" style="margin:0;">Active</label>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn">Save</button>
            <a class="btn btn-secondary" href="/warehouses">Cancel</a>
        </div>
    </form>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
