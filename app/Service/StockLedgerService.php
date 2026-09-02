<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\StockLedgerRepositoryInterface;

final class StockLedgerService
{
    public function __construct(private StockLedgerRepositoryInterface $ledger)
    {
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function search(array $filters = []): array
    {
        return $this->ledger->search($filters);
    }
}
