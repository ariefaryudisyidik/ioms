<?php
/** @var \App\Entity\Category[] $categories */
$pageTitle = 'New Product';
include __DIR__ . '/../partials/header.php';
partial('product-form', [
    'heading' => $pageTitle,
    'action' => '/products',
    'isEdit' => false,
    'product' => null,
    'active' => true,
    'imageLabel' => 'Image (JPEG/PNG/WEBP, max 2MB)',
    'categoryOptions' => array_map(static fn ($cat) => [$cat->id, $cat->name], $categories),
    'defaults' => ['sku' => '', 'name' => '', 'category_id' => '', 'unit' => 'pcs', 'purchase_price' => '', 'selling_price' => '', 'reorder_point' => '0'],
    'errors' => $errors ?? [],
]);
include __DIR__ . '/../partials/footer.php';
