<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StockLedger;
use PDO;

final class MySqlStockLedgerRepository implements StockLedgerRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function record(StockLedger $entry, ?PDO $pdo = null): StockLedger
    {
        $conn = $pdo ?? $this->pdo;
        $stmt = $conn->prepare(
            'INSERT INTO stock_ledger (product_id, warehouse_id, movement_type, quantity, reference_type, reference_id, performed_by)
             VALUES (?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $entry->productId,
            $entry->warehouseId,
            $entry->movementType,
            $entry->quantity,
            $entry->referenceType,
            $entry->referenceId,
            $entry->performedBy,
        ]);
        $entry->id = (int) $conn->lastInsertId();

        return $entry;
    }

    public function search(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['product_id'])) {
            $where[] = 'product_id = ?';
            $params[] = (int) $filters['product_id'];
        }
        if (!empty($filters['warehouse_id'])) {
            $where[] = 'warehouse_id = ?';
            $params[] = (int) $filters['warehouse_id'];
        }
        if (!empty($filters['movement_type'])) {
            $where[] = 'movement_type = ?';
            $params[] = (string) $filters['movement_type'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $sql = 'SELECT * FROM stock_ledger';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC';

        $limit = (int) ($filters['limit'] ?? 100);
        $offset = (int) ($filters['offset'] ?? 0);
        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(fn ($r) => StockLedger::fromRow($r), $stmt->fetchAll());
    }
}
