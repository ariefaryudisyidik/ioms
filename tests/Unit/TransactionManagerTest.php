<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repository\InMemoryTransactionManager;
use App\Repository\PdoTransactionManager;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TransactionManagerTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE stock (qty INTEGER)');
    }

    private function rows(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM stock')->fetchColumn();
    }

    public function testPdoManagerCommitsWhenTheWorkSucceedsAndReturnsItsResult(): void
    {
        $manager = new PdoTransactionManager($this->pdo);

        $result = $manager->run(function (): string {
            $this->pdo->exec('INSERT INTO stock VALUES (1)');
            $this->pdo->exec('INSERT INTO stock VALUES (2)');

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertSame(2, $this->rows());
        $this->assertFalse($this->pdo->inTransaction());
    }

    public function testPdoManagerRollsBackEverythingAndRethrowsWhenTheWorkFails(): void
    {
        $manager = new PdoTransactionManager($this->pdo);

        try {
            $manager->run(function (): void {
                $this->pdo->exec('INSERT INTO stock VALUES (1)');

                throw new RuntimeException('boom');
            });
            $this->fail('The failure must propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame(0, $this->rows(), 'the earlier insert was rolled back');
        $this->assertFalse($this->pdo->inTransaction());
    }

    public function testInMemoryManagerCountsCommitsAndRollbacks(): void
    {
        $manager = new InMemoryTransactionManager();

        $this->assertSame(7, $manager->run(static fn (): int => 7));
        try {
            $manager->run(static function (): void {
                throw new RuntimeException('fail');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(1, $manager->commits);
        $this->assertSame(1, $manager->rollbacks);
    }
}
