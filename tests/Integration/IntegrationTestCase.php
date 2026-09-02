<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that talk to a real MySQL instance via PDO, using
 * the real MySql* repositories (no fakes). These tests exercise:
 *   - goods receipt increasing product_stocks + writing a stock_ledger row
 *   - a second goods issue being rejected once stock is exhausted
 *   - PO status becoming PartiallyReceived then Received across two receipts
 *
 * Connection settings come from TEST_DB_* environment variables (falling
 * back to sensible docker-compose-style defaults). If no MySQL server is
 * reachable, every test in a subclass is skipped (not failed) so the suite
 * stays green in sandboxes without a database.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected static ?PDO $pdo = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$pdo === null) {
            self::$pdo = $this->connect();
        }

        if (self::$pdo === null) {
            $this->markTestSkipped('No MySQL connection available for integration tests (set TEST_DB_HOST/TEST_DB_DATABASE/etc).');
        }

        $this->resetSchema();
    }

    private function connect(): ?PDO
    {
        $host = getenv('TEST_DB_HOST') ?: 'mysql';
        $port = getenv('TEST_DB_PORT') ?: '3306';
        $db = getenv('TEST_DB_DATABASE') ?: 'ioms_test';
        $user = getenv('TEST_DB_USERNAME') ?: 'root';
        $pass = getenv('TEST_DB_PASSWORD') ?: '';

        $hosts = [$host];
        if ($host !== '127.0.0.1') {
            $hosts[] = '127.0.0.1'; // fall back for a non-docker local run
        }

        foreach ($hosts as $h) {
            try {
                $pdo = new PDO(
                    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $h, $port, $db),
                    $user,
                    $pass,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_TIMEOUT => 2,
                    ]
                );

                return $pdo;
            } catch (PDOException) {
                continue;
            }
        }

        return null;
    }

    /**
     * Loads the schema (idempotent, CREATE TABLE IF NOT EXISTS) and wipes
     * all tables so each test starts from a clean slate.
     */
    private function resetSchema(): void
    {
        $pdo = self::$pdo;
        $schemaPath = dirname(__DIR__, 2) . '/database/schema.sql';
        $sql = file_get_contents($schemaPath);
        if ($sql !== false) {
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                if ($stmt === '' || str_starts_with($stmt, '--')) {
                    continue;
                }
                $pdo->exec($stmt);
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['stock_ledger', 'sales_order_items', 'sales_orders', 'purchase_order_items', 'purchase_orders', 'product_stocks', 'products', 'categories', 'customers', 'suppliers', 'warehouses', 'users'] as $table) {
            $pdo->exec("TRUNCATE TABLE {$table}");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function pdo(): PDO
    {
        return self::$pdo;
    }

    protected function seedBaseline(): void
    {
        $pdo = $this->pdo();
        $pdo->exec("INSERT INTO users (id, name, email, password_hash, role) VALUES (1, 'Admin', 'admin@test.local', 'x', 'Admin')");
        $pdo->exec("INSERT INTO warehouses (id, name) VALUES (1, 'Main Warehouse')");
        $pdo->exec("INSERT INTO categories (id, name) VALUES (1, 'General')");
        $pdo->exec("INSERT INTO suppliers (id, name) VALUES (1, 'Test Supplier')");
        $pdo->exec("INSERT INTO customers (id, name) VALUES (1, 'Test Customer')");
        $pdo->exec("INSERT INTO products (id, sku, name, category_id, unit, purchase_price, selling_price, reorder_point)
                     VALUES (1, 'SKU-1', 'Test Product', 1, 'pcs', 100, 150, 5)");
    }
}
