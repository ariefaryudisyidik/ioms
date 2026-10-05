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
        <li><a href="/dashboard"<?= $isActive('/dashboard') ?>><?= icon('layout-dashboard') ?>Dashboard</a></li>
        <li><a href="/products"<?= $isActive('/products') ?>><?= icon('package') ?>Products</a></li>
        <?php if ($role === 'Admin'): ?>
            <li><a href="/categories"<?= $isActive('/categories') ?>><?= icon('tags') ?>Categories</a></li>
        <?php endif; ?>
        <?php if (in_array($role, ['Admin', 'WarehouseStaff'], true)): ?>
            <li><a href="/warehouses"<?= $isActive('/warehouses') ?>><?= icon('warehouse') ?>Warehouses</a></li>
        <?php endif; ?>
        <?php if ($role === 'Admin'): ?>
            <li><a href="/suppliers"<?= $isActive('/suppliers') ?>><?= icon('truck') ?>Suppliers</a></li>
        <?php endif; ?>
        <?php if (in_array($role, ['Admin', 'Sales'], true)): ?>
            <li><a href="/customers"<?= $isActive('/customers') ?>><?= icon('contact') ?>Customers</a></li>
        <?php endif; ?>

        <li class="nav-section-title">Orders</li>
        <?php if (in_array($role, ['Admin', 'WarehouseStaff'], true)): ?>
            <li><a href="/purchase-orders"<?= $isActive('/purchase-orders') ?>><?= icon('clipboard-list') ?>Purchase Orders</a></li>
        <?php endif; ?>
        <li><a href="/sales-orders"<?= $isActive('/sales-orders') ?>><?= icon('shopping-cart') ?>Sales Orders</a></li>

        <li class="nav-section-title">System</li>
        <li><a href="/reports"<?= $isActive('/reports') ?>><?= icon('chart-column') ?>Reports</a></li>
        <?php if ($role === 'Admin'): ?>
            <li><a href="/users"<?= $isActive('/users') ?>><?= icon('user-cog') ?>Users</a></li>
        <?php endif; ?>
    </ul>
    <div class="nav-footer">
        <div class="nav-user">
            <span class="nav-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) ($auth_user['name'] ?? '?'), 0, 1))) ?></span>
            <span class="nav-user-text">
                <strong><?= e($auth_user['name'] ?? '') ?></strong>
                <small><?= e($role ?? '') ?></small>
            </span>
        </div>
        <form method="post" action="/logout">
            <button type="submit" class="btn btn-secondary"><?= icon('log-out') ?>Logout</button>
        </form>
    </div>
</nav>
