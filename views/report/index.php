<?php
$pageTitle = 'Reports';
$role = $auth_user['role'] ?? '';
$today = date('Y-m-d');
$monthAgo = date('Y-m-d', strtotime('-30 days'));
include __DIR__ . '/../partials/header.php';
?>
<h1>Reports</h1>

<div class="panel">
    <h2>Stock Ledger</h2>
    <p class="text-muted">All stock movements (receipts, issues, adjustments) within a date range.</p>
    <form method="get" action="/reports/stock-ledger.csv" data-validate novalidate>
        <div class="form-grid">
            <div class="field">
                <label for="ledger_date_from">Date From</label>
                <input type="date" id="ledger_date_from" name="date_from" data-type="date" value="<?= e($monthAgo) ?>">
            </div>
            <div class="field">
                <label for="ledger_date_to">Date To</label>
                <input type="date" id="ledger_date_to" name="date_to" data-type="date" value="<?= e($today) ?>">
            </div>
        </div>
        <button type="submit" class="btn">Download CSV</button>
    </form>
</div>

<?php if (in_array($role, ['Admin', 'WarehouseStaff'], true)): ?>
<div class="panel">
    <h2>Purchase Orders</h2>
    <p class="text-muted">Purchase order status export within a date range.</p>
    <form method="get" action="/reports/orders.csv" data-validate novalidate>
        <input type="hidden" name="type" value="purchase">
        <div class="form-grid">
            <div class="field">
                <label for="po_date_from">Date From</label>
                <input type="date" id="po_date_from" name="date_from" data-type="date" value="<?= e($monthAgo) ?>">
            </div>
            <div class="field">
                <label for="po_date_to">Date To</label>
                <input type="date" id="po_date_to" name="date_to" data-type="date" value="<?= e($today) ?>">
            </div>
        </div>
        <button type="submit" class="btn">Download CSV</button>
    </form>
</div>
<?php endif; ?>

<div class="panel">
    <h2>Sales Orders</h2>
    <p class="text-muted">
        <?= $role === 'Sales' ? 'Export of your own sales orders within a date range.' : 'Sales order status export within a date range.' ?>
    </p>
    <form method="get" action="/reports/orders.csv" data-validate novalidate>
        <input type="hidden" name="type" value="sales">
        <div class="form-grid">
            <div class="field">
                <label for="so_date_from">Date From</label>
                <input type="date" id="so_date_from" name="date_from" data-type="date" value="<?= e($monthAgo) ?>">
            </div>
            <div class="field">
                <label for="so_date_to">Date To</label>
                <input type="date" id="so_date_to" name="date_to" data-type="date" value="<?= e($today) ?>">
            </div>
        </div>
        <button type="submit" class="btn">Download CSV</button>
    </form>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
