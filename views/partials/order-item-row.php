<?php
/**
 * One order line (also used for the JS <template>).
 *
 * @var string $index
 * @var string $productOptions pre-rendered, escaped <option> markup
 * @var string $qtyName
 * @var string $qtyLabel
 */
?>
<div class="item-row js-item-row">
    <div class="field">
        <label>Product
        <select name="items[<?= e($index) ?>][product_id]" class="js-product-select" data-required required>
            <option value="">Select a product</option>
            <?= $productOptions ?>
        </select>
    </label>
    </div>
    <div class="field">
        <label><?= e($qtyLabel) ?>
        <input type="number" name="items[<?= e($index) ?>][<?= e($qtyName) ?>]" class="js-qty-input" placeholder="Enter quantity" min="1" step="1" data-required data-type="number" data-min="1" required>
    </label>
    </div>
    <div class="field field-action">
        <button type="button" class="btn btn-danger btn-sm js-remove-item-row"><?= icon('trash-2') ?>Remove</button>
    </div>
    <div class="availability-box" style="grid-column:1/-1;"></div>
</div>
