<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use Throwable;

/**
 * Runs the work in one PDO transaction. Every repository that takes part
 * must use the same PDO connection (the app shares Database::connection()).
 */
final class PdoTransactionManager implements TransactionManagerInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function run(callable $work): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $work();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }
}
