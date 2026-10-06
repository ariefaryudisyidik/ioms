<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Read-only queries for the CSV exports. Rows are already resolved to
 * human-readable values (names, order numbers) instead of raw foreign keys.
 */
interface ReportRepositoryInterface
{
    /**
     * @param array{date_from?:?string,date_to?:?string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function stockLedgerRows(array $filters): array;

    /**
     * @param array{date_from?:?string,date_to?:?string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function purchaseOrderRows(array $filters): array;

    /**
     * @param array{date_from?:?string,date_to?:?string,created_by?:?int} $filters
     * @return array<int, array<string, mixed>>
     */
    public function salesOrderRows(array $filters): array;
}
