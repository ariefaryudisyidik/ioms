<?php
/** @var \App\Entity\Product $product */
/** @var \App\Entity\Category[] $categories */
$pageTitle = 'Edit Product';
$errors = $errors ?? [];
$old = [
    'sku' => \App\Core\Session::old('sku', $product->sku),
    'name' => \App\Core\Session::old('name', $product->name),
    'category_id' => \App\Core\Session::old('category_id', (string) $product->categoryId),
    'unit' => \App\Core\Session::old('unit', $product->unit),
    'purchase_price' => \App\Core\Session::old('purchase_price', (string) $product->purchasePrice),
    'selling_price' => \App\Core\Session::old('selling_price', (string) $product->sellingPrice),
    'reorder_point' => \App\Core\Session::old('reorder_point', (string) $product->reorderPoint),
];
include __DIR__ . '/../partials/header.php';
?>
<h1>Edit Product</h1>
<?php include __DIR__ . '/../partials/form-errors.php'; ?>
<div class="panel">
    <?php if ($product->imagePath): ?>
        <img class="product-image" src="/uploads/<?= e($product->imagePath) ?>" alt="<?= e($product->name) ?>" style="margin-bottom:16px;">
    <?php endif; ?>
    <form method="post" action="/products/<?= (int) $product->id ?>" enctype="multipart/form-data" data-validate novalidate>
        <input type="hidden" name="_method" value="PUT">
        <div class="form-grid">
            <div class="field<?= isset($errors['sku']) ? ' has-error' : '' ?>">
                <label for="sku">SKU</label>
                <input type="text" id="sku" name="sku" data-required value="<?= e($old['sku']) ?>" required>
                <?php if (isset($errors['sku'])): ?><div class="field-error"><?= e($errors['sku']) ?></div><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" data-required value="<?= e($old['name']) ?>" required>
                <?php if (isset($errors['name'])): ?><div class="field-error"><?= e($errors['name']) ?></div><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['category_id']) ? ' has-error' : '' ?>">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" data-required required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat->id ?>" <?= (string) $old['category_id'] === (string) $cat->id ? 'selected' : '' ?>><?= e($cat->name) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['category_id'])): ?><div class="field-error"><?= e($errors['category_id']) ?></div><?php endif; ?>
            </div>
            <div class="field">
                <label for="unit">Unit</label>
                <input type="text" id="unit" name="unit" value="<?= e($old['unit']) ?>">
            </div>
            <div class="field<?= isset($errors['purchase_price']) ? ' has-error' : '' ?>">
                <label for="purchase_price">Purchase Price</label>
                <input type="number" id="purchase_price" name="purchase_price" step="0.01" min="0" data-required data-type="number" data-min="0" value="<?= e($old['purchase_price']) ?>" required>
                <?php if (isset($errors['purchase_price'])): ?><div class="field-error"><?= e($errors['purchase_price']) ?></div><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['selling_price']) ? ' has-error' : '' ?>">
                <label for="selling_price">Selling Price</label>
                <input type="number" id="selling_price" name="selling_price" step="0.01" min="0" data-required data-type="number" data-min="0" value="<?= e($old['selling_price']) ?>" required>
                <?php if (isset($errors['selling_price'])): ?><div class="field-error"><?= e($errors['selling_price']) ?></div><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['reorder_point']) ? ' has-error' : '' ?>">
                <label for="reorder_point">Reorder Point</label>
                <input type="number" id="reorder_point" name="reorder_point" step="1" min="0" data-required data-type="number" data-min="0" value="<?= e($old['reorder_point']) ?>" required>
                <?php if (isset($errors['reorder_point'])): ?><div class="field-error"><?= e($errors['reorder_point']) ?></div><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['image']) ? ' has-error' : '' ?>">
                <label for="image">Replace Image (JPEG/PNG/WEBP, max 2MB)</label>
                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
                <?php if (isset($errors['image'])): ?><div class="field-error"><?= e($errors['image']) ?></div><?php endif; ?>
            </div>
        </div>
        <div class="field checkbox-field">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" id="is_active" name="is_active" value="1" <?= $product->isActive ? 'checked' : '' ?>>
            <label for="is_active" style="margin:0;">Active</label>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn">Save</button>
            <a class="btn btn-secondary" href="/products">Cancel</a>
        </div>
    </form>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
