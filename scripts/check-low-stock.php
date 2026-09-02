<?php

declare(strict_types=1);

/**
 * CLI utility: reports products whose total stock across all warehouses
 * has fallen below their reorder point. Intended to be run via cron, e.g.:
 *   php scripts/check-low-stock.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;
use App\Repository\MySqlProductStockRepository;

$config = require dirname(__DIR__) . '/config/config.php';

try {
    $pdo = Database::connection();
} catch (\Throwable $e) {
    fwrite(STDERR, "Could not connect to the database: {$e->getMessage()}\n");
    exit(1);
}

$stocks = new MySqlProductStockRepository($pdo);
$lowStock = $stocks->lowStockList();

if (count($lowStock) === 0) {
    echo "No low-stock products found.\n";
    exit(0);
}

echo "Low-stock products (" . count($lowStock) . "):\n";
printf("%-12s %-40s %10s %10s\n", 'SKU', 'Name', 'Total', 'ReorderPt');
foreach ($lowStock as $row) {
    printf("%-12s %-40s %10d %10d\n", $row['sku'], $row['name'], $row['total'], $row['reorder_point']);
}

exit(0);
