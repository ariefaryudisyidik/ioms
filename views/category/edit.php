<?php
/** @var \App\Entity\Category $category */
/** @var array<string,string> $errors */
$pageTitle = 'Edit Category';
$errors = $errors ?? [];
$old = [
    'name' => \App\Core\Session::old('name', $category->name),
    'description' => \App\Core\Session::old('description', $category->description ?? ''),
];
include __DIR__ . '/../partials/header.php';
?>
<h1>Edit Category</h1>
<?php include __DIR__ . '/../partials/form-errors.php'; ?>
<div class="panel">
    <form method="post" action="/categories/<?= (int) $category->id ?>" data-validate novalidate>
        <input type="hidden" name="_method" value="PUT">
        <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" data-required value="<?= e($old['name']) ?>" required>
            <?php if (isset($errors['name'])): ?><div class="field-error"><?= e($errors['name']) ?></div><?php endif; ?>
        </div>
        <div class="field">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3"><?= e($old['description']) ?></textarea>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn"><?= icon('save') ?>Save</button>
            <a class="btn btn-secondary" href="/categories"><?= icon('x') ?>Cancel</a>
        </div>
    </form>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
