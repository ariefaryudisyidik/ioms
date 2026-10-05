<?php
/** @var \App\Entity\Customer[] $customers */
/** @var \App\Entity\Warehouse[] $warehouses */
/** @var \App\Entity\Product[] $products */
$pageTitle = 'New Sales Order';
include __DIR__ . '/../partials/header.php';
partial('order-form', [
    'heading' => $pageTitle,
    'action' => '/sales-orders',
    'listHref' => '/sales-orders',
    'submitLabel' => 'Save Sales Order',
    'partyField' => [
        'id' => 'customer_id',
        'label' => 'Customer',
        'placeholder' => 'Select a customer',
        'options' => array_map(static fn ($x) => [$x->id, $x->name], $customers),
    ],
    'warehouseOptions' => array_map(static fn ($w) => [$w->id, $w->name], $warehouses),
    'defaults' => ['customer_id' => '', 'warehouse_id' => '', 'order_date' => date('Y-m-d')],
    'errors' => $errors ?? [],
    'products' => $products,
    'qtyField' => ['qty', 'Qty'],
]);
include __DIR__ . '/../partials/footer.php';
