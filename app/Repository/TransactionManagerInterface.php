<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Persistence boundary for atomic units of work. Services wrap multi-step
 * stock changes in run() without knowing which database (or driver) sits
 * behind it: the work commits when the callable returns and rolls back,
 * rethrowing, when it throws.
 */
interface TransactionManagerInterface
{
    /**
     * @template T
     * @param callable():T $work
     * @return T
     */
    public function run(callable $work): mixed;
}
