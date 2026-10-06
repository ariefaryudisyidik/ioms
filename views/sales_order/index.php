<?php
/** @var \App\Entity\SalesOrder[] $items */
/** @var int $total */
/** @var int $page */
/** @var array $filters */
/** @var \App\Entity\Customer[] $customers */
$pageTitle = 'Sales Orders';
$role = $auth_user['role'] ?? '';
include __DIR__ . '/../partials/header.php';
partial('order-list', [
    'title' => $pageTitle,
    'baseUrl' => '/sales-orders',
    'createLabel' => '+ New Sales Order',
    'createRoles' => ['Admin', 'Sales'],
    'role' => $role,
    'statuses' => ['Draft', 'PendingApproval', 'Approved', 'Fulfilled', 'Cancelled'],
    'filters' => $filters,
    'extraFilter' => [
        'id' => 'customer_id',
        'label' => 'Customer',
        'options' => array_map(static fn ($customer) => [$customer->id, $customer->name], $customers),
    ],
    'notice' => null,
    'emptyMessage' => 'Belum ada data sales order.',
    'headers' => ['SO Number', 'Customer', 'Order Date', 'Status', ''],
    'rows' => array_map(static fn ($o) => [
        e($o->soNumber),
        e($o->customerName ?? '-'),
        e($o->orderDate),
        '<span class="badge badge-' . strtolower($o->status) . '">' . e($o->status) . '</span>',
        '<a class="btn btn-secondary btn-sm" href="/sales-orders/' . (int) $o->id . '">' . icon('eye') . 'View</a>',
    ], $items),
    'page' => $page,
    'total' => $total,
]);
include __DIR__ . '/../partials/footer.php';
