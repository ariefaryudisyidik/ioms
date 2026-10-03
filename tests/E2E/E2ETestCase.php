<?php

declare(strict_types=1);

namespace Tests\E2E;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base class for black-box tests against a running app (E2E_BASE_URL) backed
 * by a throwaway MySQL (E2E_DB_*). The database is reset to schema + demo seed
 * before every test. Skipped automatically when E2E_BASE_URL is not set.
 */
abstract class E2ETestCase extends TestCase
{
    protected const PASSWORD = 'Password123!';
    protected const ADMIN = 'admin@ioms.test';
    protected const SALES = 'sari.sales@ioms.test';
    protected const SALES_2 = 'budi.sales@ioms.test';
    protected const WAREHOUSE = 'rudi.warehouse@ioms.test';

    private static ?PDO $pdo = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('E2E_BASE_URL') === false || getenv('E2E_BASE_URL') === '') {
            $this->markTestSkipped('E2E_BASE_URL is not set (run scripts/coverage.sh or start the app first).');
        }

        $this->resetDatabase();
    }

    protected function client(): HttpClient
    {
        return new HttpClient((string) getenv('E2E_BASE_URL'));
    }

    protected function loginAs(string $email): HttpClient
    {
        $client = $this->client();
        $response = $client->post('/login', ['email' => $email, 'password' => self::PASSWORD]);
        $this->assertSame(302, $response->status, "login as {$email}");

        return $client;
    }

    protected function db(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = new PDO(
                sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    getenv('E2E_DB_HOST') ?: '127.0.0.1',
                    getenv('E2E_DB_PORT') ?: '3306',
                    getenv('E2E_DB_DATABASE') ?: 'ioms',
                ),
                getenv('E2E_DB_USERNAME') ?: 'root',
                getenv('E2E_DB_PASSWORD') ?: '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
            );
        }

        return self::$pdo;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    protected function rows(string $sql, array $params = []): array
    {
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    protected function value(string $sql, array $params = []): mixed
    {
        $rows = $this->rows($sql, $params);

        return $rows === [] ? null : array_values($rows[0])[0];
    }

    /**
     * Writes bytes to a temp file and returns an upload wrapper for it.
     */
    protected function upload(string $bytes, string $name, string $mime): \CURLFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'e2e-upload');
        file_put_contents($path, $bytes);

        return new \CURLFile($path, $mime, $name);
    }

    protected function png(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        );
    }

    protected function jpeg(): string
    {
        return (string) base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='
        );
    }

    protected function webp(): string
    {
        return (string) base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA');
    }

    private function resetDatabase(): void
    {
        $pdo = $this->db();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->rows('SHOW TABLES') as $row) {
            $pdo->exec('TRUNCATE TABLE `' . array_values($row)[0] . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $seed = file_get_contents(dirname(__DIR__, 2) . '/database/seed.sql');
        $pdo->exec((string) $seed);
    }
}
