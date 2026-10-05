<?php
/** @var \App\Entity\PurchaseOrder[] $items */
/** @var int $total */
/** @var int $page */
/** @var array $filters */
/** @var \App\Entity\Supplier[] $suppliers */
$pageTitle = 'Purchase Orders';
$role = $auth_user['role'] ?? '';
include __DIR__ . '/../partials/header.php';
partial('order-list', [
    'title' => $pageTitle,
    'baseUrl' => '/purchase-orders',
    'createLabel' => '+ New Purchase Order',
    'createRoles' => ['Admin', 'WarehouseStaff'],
    'role' => $role,
    'statuses' => ['Draft', 'Ordered', 'PartiallyReceived', 'Received', 'Cancelled'],
    'filters' => $filters,
    'extraFilter' => [
        'id' => 'supplier_id',
        'label' => 'Supplier',
        'options' => array_map(static fn ($sup) => [$sup->id, $sup->name], $suppliers),
    ],
    'notice' => null,
    'emptyMessage' => 'Belum ada data purchase order.',
    'headers' => ['PO Number', 'Supplier', 'Order Date', 'Status', ''],
    'rows' => array_map(static fn ($o) => [
        e($o->poNumber),
        e($o->supplierName ?? '-'),
        e($o->orderDate),
        '<span class="badge badge-' . strtolower($o->status) . '">' . e($o->status) . '</span>',
        '<a class="btn btn-secondary btn-sm" href="/purchase-orders/' . (int) $o->id . '">' . icon('eye') . 'View</a>',
    ], $items),
    'page' => $page,
    'total' => $total,
]);
include __DIR__ . '/../partials/footer.php';
