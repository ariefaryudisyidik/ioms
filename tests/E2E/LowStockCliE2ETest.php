<?php

declare(strict_types=1);

namespace Tests\E2E;

/**
 * scripts/check-low-stock.php is run as a real CLI process (as cron would).
 */
final class LowStockCliE2ETest extends E2ETestCase
{
    /**
     * @return array{0:int,1:string,2:string}
     */
    private function runScript(array $env = []): array
    {
        $root = dirname(__DIR__, 2);
        $childEnv = array_merge(getenv(), [
            'DB_HOST' => getenv('E2E_DB_HOST') ?: '127.0.0.1',
            'DB_PORT' => getenv('E2E_DB_PORT') ?: '3306',
            'DB_DATABASE' => getenv('E2E_DB_DATABASE') ?: 'ioms',
            'DB_USERNAME' => getenv('E2E_DB_USERNAME') ?: 'root',
            'DB_PASSWORD' => getenv('E2E_DB_PASSWORD') ?: '',
            'E2E_COVERAGE_DIR' => getenv('E2E_CHILD_COVERAGE_DIR') ?: '',
            'XDEBUG_MODE' => getenv('E2E_CHILD_COVERAGE_DIR') ? 'coverage' : 'off',
        ], $env);

        $command = [PHP_BINARY, '-d', 'variables_order=EGPCS'];
        if ($childEnv['E2E_COVERAGE_DIR'] !== '') {
            $command[] = '-d';
            $command[] = 'auto_prepend_file=' . $root . '/tests/support/coverage-prepend.php';
        }
        $command[] = $root . '/scripts/check-low-stock.php';

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $childEnv);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }

    public function testListsProductsBelowReorderPoint(): void
    {
        [$code, $out] = $this->runScript();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Low-stock products', $out);
        $this->assertStringContainsString('SKU-0005', $out);
    }

    public function testReportsWhenNothingIsLow(): void
    {
        $this->db()->exec('UPDATE product_stocks SET quantity = 100000');

        [$code, $out] = $this->runScript();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('No low-stock products found.', $out);
    }

    public function testExitsWithErrorWhenDatabaseIsUnreachable(): void
    {
        [$code, , $err] = $this->runScript(['DB_PORT' => '1']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Could not connect to the database', $err);
    }
}
