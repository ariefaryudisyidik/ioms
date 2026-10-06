<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repository\StockLedgerRepositoryInterface;

/**
 * Minimal stock ledger fake sufficient for these unit tests.
 */
final class FakeStockLedgerRepository implements StockLedgerRepositoryInterface
{
    public function record(\App\Entity\StockLedger $entry): \App\Entity\StockLedger
    {
        $entry->id = random_int(1, 1000000);

        return $entry;
    }

    public function search(array $filters = []): array
    {
        return [];
    }
}
