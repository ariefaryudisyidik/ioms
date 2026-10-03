<?php
$hasError = ' has-error';
/** @var \App\Entity\Supplier[] $suppliers */
/** @var \App\Entity\Warehouse[] $warehouses */
/** @var \App\Entity\Product[] $products */
$pageTitle = 'New Purchase Order';
$errors = $errors ?? [];
$old = [
    'po_number' => \App\Core\Session::old('po_number', ''),
    'supplier_id' => \App\Core\Session::old('supplier_id', ''),
    'warehouse_id' => \App\Core\Session::old('warehouse_id', ''),
    'order_date' => \App\Core\Session::old('order_date', date('Y-m-d')),
];

$renderProductOptions = static function () use ($products) {
    foreach ($products as $p) {
        echo '<option value="' . (int) $p->id . '" data-sku="' . e($p->sku) . '" data-price="' . e((string) $p->purchasePrice) . '">'
            . e($p->name) . ' (' . e($p->sku) . ')</option>';
    }
};

include __DIR__ . '/../partials/header.php';
?>
<h1>New Purchase Order</h1>
<?php include __DIR__ . '/../partials/form-errors.php'; ?>
<div class="panel">
    <form method="post" action="/purchase-orders" data-validate novalidate>
        <div class="form-grid">
            <div class="field<?= isset($errors['po_number']) ? $hasError : '' ?>">
                <label for="po_number">PO Number</label>
                <input type="text" id="po_number" name="po_number" data-required value="<?= e($old['po_number']) ?>" required>
                <?php if (isset($errors['po_number'])): ?><div class="field-error"><?= e($errors['po_number']) ?></div><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['supplier_id']) ? $hasError : '' ?>">
                <label for="supplier_id">Supplier</label>
                <select id="supplier_id" name="supplier_id" data-required required>
                    <option value="">Select a supplier</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int) $s->id ?>" <?= (string) $old['supplier_id'] === (string) $s->id ? 'selected' : '' ?>><?= e($s->name) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['supplier_id'])): ?><div class="field-error"><?= e($errors['supplier_id']) ?></div><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['warehouse_id']) ? $hasError : '' ?>">
                <label for="warehouse_id">Warehouse</label>
                <select id="warehouse_id" name="warehouse_id" class="js-order-warehouse" data-required required>
                    <option value="">Select a warehouse</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?= (int) $w->id ?>" <?= (string) $old['warehouse_id'] === (string) $w->id ? 'selected' : '' ?>><?= e($w->name) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['warehouse_id'])): ?><div class="field-error"><?= e($errors['warehouse_id']) ?></div><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['order_date']) ? $hasError : '' ?>">
                <label for="order_date">Order Date</label>
                <input type="date" id="order_date" name="order_date" data-required data-type="date" value="<?= e($old['order_date']) ?>" required>
                <?php if (isset($errors['order_date'])): ?><div class="field-error"><?= e($errors['order_date']) ?></div><?php endif; ?>
            </div>
        </div>

        <fieldset>
            <legend>Items</legend>
            <?php if (isset($errors['items'])): ?><div class="field-error"><?= e($errors['items']) ?></div><?php endif; ?>
            <div class="js-item-rows">
                <div class="item-row js-item-row">
                    <div class="field">
                        <label>Product
                        <select name="items[0][product_id]" class="js-product-select" data-required required>
                            <option value="">Select a product</option>
                            <?php $renderProductOptions(); ?>
                        </select>
                    </label>
                    </div>
                    <div class="field">
                        <label>Qty Ordered
                        <input type="number" name="items[0][qty_ordered]" class="js-qty-input" min="1" step="1" data-required data-type="number" data-min="1" required>
                    </label>
                    </div>
                    <div class="field">
                        <label>Purchase Price
                        <input type="number" name="items[0][purchase_price]" min="0" step="0.01" data-type="number" data-min="0">
                    </label>
                    </div>
                    <div class="field">
                        <button type="button" class="btn btn-danger btn-sm js-remove-item-row">Remove</button>
                    </div>
                    <div class="availability-box" style="grid-column:1/-1;"></div>
                </div>
            </div>
            <button type="button" class="btn btn-secondary btn-sm js-add-item-row">+ Add Item</button>
        </fieldset>

        <div class="btn-row">
            <button type="submit" class="btn">Save Purchase Order</button>
            <a class="btn btn-secondary" href="/purchase-orders">Cancel</a>
        </div>
    </form>
</div>

<template class="js-item-row-template">
    <div class="item-row js-item-row">
        <div class="field">
            <label>Product
            <select name="items[__INDEX__][product_id]" class="js-product-select" data-required required>
                <option value="">Select a product</option>
                <?php $renderProductOptions(); ?>
            </select>
        </label>
        </div>
        <div class="field">
            <label>Qty Ordered
            <input type="number" name="items[__INDEX__][qty_ordered]" class="js-qty-input" min="1" step="1" data-required data-type="number" data-min="1" required>
        </label>
        </div>
        <div class="field">
            <label>Purchase Price
            <input type="number" name="items[__INDEX__][purchase_price]" min="0" step="0.01" data-type="number" data-min="0">
        </label>
        </div>
        <div class="field">
            <button type="button" class="btn btn-danger btn-sm js-remove-item-row">Remove</button>
        </div>
        <div class="availability-box" style="grid-column:1/-1;"></div>
    </div>
</template>
<?php include __DIR__ . '/../partials/footer.php'; ?>
