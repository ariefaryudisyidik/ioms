<?php
/** @var \App\Entity\SalesOrder[] $items */
/** @var int $total */
/** @var int $page */
/** @var array $filters */
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
    'extraFilter' => null,
    'notice' => $role === 'Sales' ? 'Showing only sales orders you created.' : null,
    'emptyMessage' => 'Belum ada data sales order.',
    'headers' => ['SO Number', 'Order Date', 'Status', ''],
    'rows' => array_map(static fn ($o) => [
        e($o->soNumber),
        e($o->orderDate),
        '<span class="badge badge-' . strtolower($o->status) . '">' . e($o->status) . '</span>',
        '<a class="btn btn-secondary btn-sm" href="/sales-orders/' . (int) $o->id . '">View</a>',
    ], $items),
    'page' => $page,
    'total' => $total,
]);
include __DIR__ . '/../partials/footer.php';
