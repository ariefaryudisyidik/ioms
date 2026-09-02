<?php
/** @var \App\Entity\Customer[] $customers */
/** @var \App\Entity\Warehouse[] $warehouses */
/** @var \App\Entity\Product[] $products */
$pageTitle = 'New Sales Order';
$errors = $errors ?? [];
$old = [
    'so_number' => \App\Core\Session::old('so_number', ''),
    'customer_id' => \App\Core\Session::old('customer_id', ''),
    'warehouse_id' => \App\Core\Session::old('warehouse_id', ''),
    'order_date' => \App\Core\Session::old('order_date', date('Y-m-d')),
];

$renderProductOptions = static function () use ($products) {
    foreach ($products as $p) {
        echo '<option value="' . (int) $p->id . '" data-sku="' . e($p->sku) . '" data-price="' . e((string) $p->sellingPrice) . '">'
            . e($p->name) . ' (' . e($p->sku) . ')</option>';
    }
};

include __DIR__ . '/../partials/header.php';
?>
<h1>New Sales Order</h1>
<?php include __DIR__ . '/../partials/form-errors.php'; ?>
<div class="panel">
    <form method="post" action="/sales-orders" data-validate novalidate>
        <div class="form-grid">
            <div class="field<?= isset($errors['so_number']) ? ' has-error' : '' ?>">
                <label for="so_number">SO Number</label>
                <input type="text" id="so_number" name="so_number" data-required value="<?= e($old['so_number']) ?>" required>
                <?php if (isset($errors['so_number'])): ?><div class="field-error"><?= e($errors['so_number']) ?></div><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['customer_id']) ? ' has-error' : '' ?>">
                <label for="customer_id">Customer</label>
                <select id="customer_id" name="customer_id" data-required required>
                    <option value="">Select a customer</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= (int) $c->id ?>" <?= (string) $old['customer_id'] === (string) $c->id ? 'selected' : '' ?>><?= e($c->name) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['customer_id'])): ?><div class="field-error"><?= e($errors['customer_id']) ?></div><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['warehouse_id']) ? ' has-error' : '' ?>">
                <label for="warehouse_id">Warehouse</label>
                <select id="warehouse_id" name="warehouse_id" class="js-order-warehouse" data-required required>
                    <option value="">Select a warehouse</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?= (int) $w->id ?>" <?= (string) $old['warehouse_id'] === (string) $w->id ? 'selected' : '' ?>><?= e($w->name) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['warehouse_id'])): ?><div class="field-error"><?= e($errors['warehouse_id']) ?></div><?php endif; ?>
            </div>
            <div class="field<?= isset($errors['order_date']) ? ' has-error' : '' ?>">
                <label for="order_date">Order Date</label>
                <input type="date" id="order_date" name="order_date" data-required data-type="date" value="<?= e($old['order_date']) ?>" required>
                <?php if (isset($errors['order_date'])): ?><div class="field-error"><?= e($errors['order_date']) ?></div><?php endif; ?>
            </div>
        </div>

        <fieldset>
            <legend>Items</legend>
            <p class="hint">Selecting a product and warehouse checks live stock availability automatically.</p>
            <?php if (isset($errors['items'])): ?><div class="field-error"><?= e($errors['items']) ?></div><?php endif; ?>
            <div class="js-item-rows">
                <div class="item-row js-item-row">
                    <div class="field">
                        <label>Product</label>
                        <select name="items[][product_id]" class="js-product-select" data-required required>
                            <option value="">Select a product</option>
                            <?php $renderProductOptions(); ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Qty</label>
                        <input type="number" name="items[][qty]" class="js-qty-input" min="1" step="1" data-required data-type="number" data-min="1" required>
                    </div>
                    <div class="field">
                        <label>Selling Price</label>
                        <input type="number" name="items[][selling_price]" min="0" step="0.01" data-type="number" data-min="0">
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
            <button type="submit" class="btn">Save Sales Order</button>
            <a class="btn btn-secondary" href="/sales-orders">Cancel</a>
        </div>
    </form>
</div>

<template class="js-item-row-template">
    <div class="item-row js-item-row">
        <div class="field">
            <label>Product</label>
            <select name="items[][product_id]" class="js-product-select" data-required required>
                <option value="">Select a product</option>
                <?php $renderProductOptions(); ?>
            </select>
        </div>
        <div class="field">
            <label>Qty</label>
            <input type="number" name="items[][qty]" class="js-qty-input" min="1" step="1" data-required data-type="number" data-min="1" required>
        </div>
        <div class="field">
            <label>Selling Price</label>
            <input type="number" name="items[][selling_price]" min="0" step="0.01" data-type="number" data-min="0">
        </div>
        <div class="field">
            <button type="button" class="btn btn-danger btn-sm js-remove-item-row">Remove</button>
        </div>
        <div class="availability-box" style="grid-column:1/-1;"></div>
    </div>
</template>
<?php include __DIR__ . '/../partials/footer.php'; ?>
