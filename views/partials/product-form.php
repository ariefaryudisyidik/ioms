<?php
/**
 * Product form shared by create and edit.
 *
 * @var string $heading
 * @var string $action
 * @var bool $isEdit
 * @var bool $active
 * @var string $imageLabel
 * @var \App\Entity\Product|null $product
 * @var list<array{0:int,1:string}> $categoryOptions
 * @var array<string,string> $defaults values used when no old input is flashed
 * @var array<string,string>|null $errors
 */
$errors ??= [];
$old = [];
foreach ($defaults as $key => $value) {
    $old[$key] = \App\Core\Session::old($key, $value);
}
$numberAttrs = static fn (string $step): string => 'step="' . $step . '" min="0" data-required data-type="number" data-min="0"';
$numberFields = [
    ['purchase_price', 'Purchase Price', '0.01'],
    ['selling_price', 'Selling Price', '0.01'],
    ['reorder_point', 'Reorder Point', '1'],
];
?>
<h1><?= e($heading) ?></h1>
<?php include __DIR__ . '/form-errors.php'; ?>
<div class="panel">
    <?php if ($isEdit && $product->imagePath): ?>
        <img class="product-image" src="/uploads/<?= e($product->imagePath) ?>" alt="<?= e($product->name) ?>" style="margin-bottom:16px;">
    <?php endif; ?>
    <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" data-validate novalidate>
        <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>
        <div class="form-grid">
            <?php partial('text-field', ['id' => 'sku', 'label' => 'SKU', 'type' => 'text', 'value' => $old['sku'], 'attrs' => 'data-required', 'required' => true, 'errorKey' => 'sku', 'errors' => $errors]); ?>
            <?php partial('text-field', ['id' => 'name', 'label' => 'Name', 'type' => 'text', 'value' => $old['name'], 'attrs' => 'data-required', 'required' => true, 'errorKey' => 'name', 'errors' => $errors]); ?>
            <?php partial('select-field', ['id' => 'category_id', 'label' => 'Category', 'placeholder' => 'Select a category', 'selectClass' => '', 'options' => $categoryOptions, 'selected' => (string) $old['category_id'], 'errors' => $errors]); ?>
            <?php partial('text-field', ['id' => 'unit', 'label' => 'Unit', 'type' => 'text', 'value' => $old['unit'], 'attrs' => '', 'required' => false, 'errorKey' => null, 'errors' => $errors]); ?>
            <?php foreach ($numberFields as [$fieldId, $fieldLabel, $step]): ?>
                <?php partial('text-field', ['id' => $fieldId, 'label' => $fieldLabel, 'type' => 'number', 'value' => $old[$fieldId], 'attrs' => $numberAttrs($step), 'required' => true, 'errorKey' => $fieldId, 'errors' => $errors]); ?>
            <?php endforeach; ?>
            <?php partial('text-field', ['id' => 'image', 'label' => $imageLabel, 'type' => 'file', 'value' => null, 'attrs' => 'accept="image/jpeg,image/png,image/webp"', 'required' => false, 'errorKey' => 'image', 'errors' => $errors]); ?>
        </div>
        <?php partial('form-footer', ['active' => $active, 'cancelHref' => '/products']); ?>
    </form>
</div>
