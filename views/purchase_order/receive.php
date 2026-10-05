<?php
/** @var \App\Entity\PurchaseOrder $po */
$pageTitle = 'Receive Goods - ' . $po->poNumber;
$pdo = \App\Core\Database::connection();
$products = (new \App\Repository\MySqlProductRepository($pdo))->all();
$productsById = [];
foreach ($products as $p) { $productsById[$p->id] = $p; }
include __DIR__ . '/../partials/header.php';
?>
<h1>Receive Goods &mdash; <?= e($po->poNumber) ?></h1>

<div class="panel">
    <form method="post" action="/purchase-orders/<?= (int) $po->id ?>/receive" data-validate novalidate>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Product</th><th>Ordered</th><th>Received</th><th>Remaining</th><th>Receive Now</th></tr></thead>
                <tbody>
                <?php foreach ($po->items as $item): ?>
                    <?php $product = $productsById[$item->productId] ?? null; ?>
                    <tr>
                        <td><?= e($product?->name ?? ('#' . $item->productId)) ?></td>
                        <td><?= (int) $item->qtyOrdered ?></td>
                        <td><?= (int) $item->qtyReceived ?></td>
                        <td><?= (int) $item->remaining() ?></td>
                        <td>
                            <input type="number" aria-label="Quantity to receive" name="items[<?= (int) $item->id ?>]" min="0" max="<?= (int) $item->remaining() ?>"
                                   step="1" value="0" data-type="number" data-min="0" <?= $item->remaining() <= 0 ? 'disabled' : '' ?>>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="btn-row" style="margin-top:16px;">
            <button type="submit" class="btn"><?= icon('package-check') ?>Confirm Receipt</button>
            <a class="btn btn-secondary" href="/purchase-orders/<?= (int) $po->id ?>"><?= icon('x') ?>Cancel</a>
        </div>
    </form>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
