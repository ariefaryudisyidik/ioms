<?php
/** @var array{id:int,name:string,email:string,role:string}|null $auth_user */
$role = $auth_user['role'] ?? null;
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$isActive = static function (string $prefix) use ($currentPath): string {
    return str_starts_with($currentPath, $prefix) ? ' class="active"' : '';
};
?>
<nav class="app-nav">
    <ul>
        <li><a href="/dashboard"<?= $isActive('/dashboard') ?>>Dashboard</a></li>
        <li><a href="/products"<?= $isActive('/products') ?>>Products</a></li>
        <?php if ($role === 'Admin'): ?>
            <li><a href="/categories"<?= $isActive('/categories') ?>>Categories</a></li>
        <?php endif; ?>
        <?php if (in_array($role, ['Admin', 'WarehouseStaff'], true)): ?>
            <li><a href="/warehouses"<?= $isActive('/warehouses') ?>>Warehouses</a></li>
        <?php endif; ?>
        <?php if ($role === 'Admin'): ?>
            <li><a href="/suppliers"<?= $isActive('/suppliers') ?>>Suppliers</a></li>
        <?php endif; ?>
        <?php if (in_array($role, ['Admin', 'Sales'], true)): ?>
            <li><a href="/customers"<?= $isActive('/customers') ?>>Customers</a></li>
        <?php endif; ?>

        <li class="nav-section-title">Orders</li>
        <?php if (in_array($role, ['Admin', 'WarehouseStaff'], true)): ?>
            <li><a href="/purchase-orders"<?= $isActive('/purchase-orders') ?>>Purchase Orders</a></li>
        <?php endif; ?>
        <li><a href="/sales-orders"<?= $isActive('/sales-orders') ?>>Sales Orders</a></li>

        <li class="nav-section-title">System</li>
        <li><a href="/reports"<?= $isActive('/reports') ?>>Reports</a></li>
        <?php if ($role === 'Admin'): ?>
            <li><a href="/users"<?= $isActive('/users') ?>>Users</a></li>
        <?php endif; ?>
    </ul>
</nav>
