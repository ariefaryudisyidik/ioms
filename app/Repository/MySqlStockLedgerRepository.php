<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StockLedger;

final class MySqlStockLedgerRepository extends AbstractMySqlRepository implements StockLedgerRepositoryInterface
{
    private const RULES = [
        ['product_id', 'product_id = ?', 'int'],
        [self::COL_WAREHOUSE_ID, 'warehouse_id = ?', 'int'],
        ['movement_type', 'movement_type = ?', 'string'],
        [self::COL_DATE_FROM, 'created_at >= ?', 'string', ' 00:00:00'],
        [self::COL_DATE_TO, 'created_at <= ?', 'string', ' 23:59:59'],
    ];

    public function record(StockLedger $entry): StockLedger
    {
        $entry->id = $this->insert(
            'INSERT INTO stock_ledger (product_id, warehouse_id, movement_type, quantity, reference_type, reference_id,
                performed_by)
             VALUES (?,?,?,?,?,?,?)',
            [
                $entry->productId,
                $entry->warehouseId,
                $entry->movementType,
                $entry->quantity,
                $entry->referenceType,
                $entry->referenceId,
                $entry->performedBy,
            ]
        );

        return $entry;
    }

    public function search(array $filters = []): array
    {
        $rows = $this->searchRows(
            'SELECT id, product_id, warehouse_id, movement_type, quantity, reference_type, reference_id,
                    performed_by, created_at
             FROM stock_ledger',
            $filters,
            self::RULES,
            'created_at DESC',
            100
        );

        return array_map(fn ($r) => StockLedger::fromRow($r), $rows);
    }
}
