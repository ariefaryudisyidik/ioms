<?php
$pageTitle = 'New Supplier';
$errors = $errors ?? [];
$old = [
    'name' => \App\Core\Session::old('name', ''),
    'contact' => \App\Core\Session::old('contact', ''),
    'address' => \App\Core\Session::old('address', ''),
];
include __DIR__ . '/../partials/header.php';
?>
<h1>New Supplier</h1>
<?php include __DIR__ . '/../partials/form-errors.php'; ?>
<div class="panel">
    <form method="post" action="/suppliers" data-validate novalidate>
        <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" data-required value="<?= e($old['name']) ?>" required>
            <?php if (isset($errors['name'])): ?><div class="field-error"><?= e($errors['name']) ?></div><?php endif; ?>
        </div>
        <div class="field">
            <label for="contact">Contact</label>
            <input type="text" id="contact" name="contact" value="<?= e($old['contact']) ?>">
        </div>
        <div class="field">
            <label for="address">Address</label>
            <textarea id="address" name="address" rows="3"><?= e($old['address']) ?></textarea>
        </div>
        <div class="field checkbox-field">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" id="is_active" name="is_active" value="1" checked>
            <label for="is_active" style="margin:0;">Active</label>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn">Save</button>
            <a class="btn btn-secondary" href="/suppliers">Cancel</a>
        </div>
    </form>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
