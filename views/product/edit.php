<?php
/** @var \App\Entity\Product $product */
/** @var \App\Entity\Category[] $categories */
$pageTitle = 'Edit Product';
include __DIR__ . '/../partials/header.php';
partial('product-form', [
    'heading' => $pageTitle,
    'action' => '/products/' . (int) $product->id,
    'isEdit' => true,
    'product' => $product,
    'active' => $product->isActive,
    'imageLabel' => 'Replace Image (JPEG/PNG/WEBP, max 2MB)',
    'categoryOptions' => array_map(static fn ($cat) => [$cat->id, $cat->name], $categories),
    'defaults' => [
        'sku' => $product->sku,
        'name' => $product->name,
        'category_id' => (string) $product->categoryId,
        'unit' => $product->unit,
        'purchase_price' => (string) $product->purchasePrice,
        'selling_price' => (string) $product->sellingPrice,
        'reorder_point' => (string) $product->reorderPoint,
    ],
    'errors' => $errors ?? [],
]);
include __DIR__ . '/../partials/footer.php';
