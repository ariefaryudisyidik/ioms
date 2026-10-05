<?php
/** @var \App\Entity\Supplier[] $suppliers */
/** @var \App\Entity\Warehouse[] $warehouses */
/** @var \App\Entity\Product[] $products */
$pageTitle = 'New Purchase Order';
include __DIR__ . '/../partials/header.php';
partial('order-form', [
    'heading' => $pageTitle,
    'action' => '/purchase-orders',
    'listHref' => '/purchase-orders',
    'submitLabel' => 'Save Purchase Order',
    'partyField' => [
        'id' => 'supplier_id',
        'label' => 'Supplier',
        'placeholder' => 'Select a supplier',
        'options' => array_map(static fn ($x) => [$x->id, $x->name], $suppliers),
    ],
    'warehouseOptions' => array_map(static fn ($w) => [$w->id, $w->name], $warehouses),
    'defaults' => ['supplier_id' => '', 'warehouse_id' => '', 'order_date' => date('Y-m-d')],
    'errors' => $errors ?? [],
    'products' => $products,
    'qtyField' => ['qty_ordered', 'Qty Ordered'],
]);
include __DIR__ . '/../partials/footer.php';
