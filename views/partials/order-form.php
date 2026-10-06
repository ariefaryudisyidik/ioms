<?php
/**
 * Create form shared by purchase and sales orders. The order number and the
 * item price are not entered: the Service generates the number and takes the
 * price from the product.
 *
 * @var string $heading
 * @var string $action
 * @var string $listHref
 * @var string $submitLabel
 * @var array{id:string,label:string,placeholder:string,options:list<array{0:int,1:string}>} $partyField
 * @var list<array{0:int,1:string}> $warehouseOptions
 * @var array<string,string> $defaults values used when no old input is flashed
 * @var array<string,string>|null $errors
 * @var \App\Entity\Product[] $products
 * @var array{0:string,1:string} $qtyField [name, label]
 * @var bool $warnOnExceed warn when qty exceeds stock (sales orders only; a PO adds stock)
 */
$errors ??= [];
$warnOnExceed ??= false;
$old = [];
foreach ($defaults as $key => $value) {
    $old[$key] = \App\Core\Session::old($key, $value);
}
$productOptions = '';
foreach ($products as $p) {
    $productOptions .= '<option value="' . (int) $p->id . '" data-sku="' . e($p->sku) . '">'
        . e($p->name) . ' (' . e($p->sku) . ')</option>';
}
$rowVars = [
    'productOptions' => $productOptions,
    'qtyName' => $qtyField[0],
    'qtyLabel' => $qtyField[1],
];
?>
<h1><?= e($heading) ?></h1>
<?php include __DIR__ . '/form-errors.php'; ?>
<div class="panel">
    <form method="post" action="<?= e($action) ?>" data-validate novalidate<?= $warnOnExceed ? ' data-warn-exceed' : '' ?>>
        <div class="form-grid">
            <?php partial('select-field', ['id' => $partyField['id'], 'label' => $partyField['label'], 'placeholder' => $partyField['placeholder'], 'selectClass' => '', 'options' => $partyField['options'], 'selected' => (string) $old[$partyField['id']], 'errors' => $errors]); ?>
            <?php partial('select-field', ['id' => 'warehouse_id', 'label' => 'Warehouse', 'placeholder' => 'Select a warehouse', 'selectClass' => 'js-order-warehouse', 'options' => $warehouseOptions, 'selected' => (string) $old['warehouse_id'], 'errors' => $errors]); ?>
            <?php partial('text-field', ['id' => 'order_date', 'label' => 'Order Date', 'type' => 'date', 'value' => $old['order_date'], 'attrs' => 'data-required data-type="date"', 'required' => true, 'errorKey' => 'order_date', 'errors' => $errors]); ?>
        </div>

        <fieldset>
            <legend>Items</legend>
            <?php if (isset($errors['items'])): ?><div class="field-error"><?= e($errors['items']) ?></div><?php endif; ?>
            <div class="js-item-rows">
                <?php partial('order-item-row', $rowVars + ['index' => '0']); ?>
            </div>
            <button type="button" class="btn btn-secondary btn-sm js-add-item-row"><?= icon('plus') ?>Add Item</button>
        </fieldset>

        <div class="btn-row">
            <button type="submit" class="btn"><?= icon('save') ?><?= e($submitLabel) ?></button>
            <a class="btn btn-secondary" href="<?= e($listHref) ?>"><?= icon('x') ?>Cancel</a>
        </div>
    </form>
</div>

<template class="js-item-row-template">
    <?php partial('order-item-row', $rowVars + ['index' => '__INDEX__']); ?>
</template>
