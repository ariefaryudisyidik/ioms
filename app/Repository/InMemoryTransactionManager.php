<?php

declare(strict_types=1);

namespace App\Repository;

use Throwable;

/**
 * Test double: runs the work directly and records how many units of work
 * committed or rolled back, so unit tests can assert transactional behaviour
 * without a database. (In-memory repositories do not undo their own writes.)
 */
final class InMemoryTransactionManager implements TransactionManagerInterface
{
    public int $commits = 0;
    public int $rollbacks = 0;

    public function run(callable $work): mixed
    {
        try {
            $result = $work();
        } catch (Throwable $e) {
            $this->rollbacks++;

            throw $e;
        }

        $this->commits++;

        return $result;
    }
}
