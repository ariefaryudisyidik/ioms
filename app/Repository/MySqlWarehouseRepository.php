<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Warehouse;
use PDO;

final class MySqlWarehouseRepository implements WarehouseRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?Warehouse
    {
        $stmt = $this->pdo->prepare('SELECT id, name, location, is_active FROM warehouses WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? Warehouse::fromRow($row) : null;
    }

    public function all(bool $onlyActive = false): array
    {
        $sql = 'SELECT id, name, location, is_active FROM warehouses'
            . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY name ASC';
        $stmt = $this->pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll() : [];

        return array_map(fn ($r) => Warehouse::fromRow($r), $rows);
    }

    public function save(Warehouse $warehouse): Warehouse
    {
        if ($warehouse->id === null) {
            $stmt = $this->pdo->prepare('INSERT INTO warehouses (name, location, is_active) VALUES (?,?,?)');
            $stmt->execute([$warehouse->name, $warehouse->location, (int) $warehouse->isActive]);
            $warehouse->id = (int) $this->pdo->lastInsertId();

            return $warehouse;
        }

        $stmt = $this->pdo->prepare('UPDATE warehouses SET name=?, location=?, is_active=? WHERE id=?');
        $stmt->execute([$warehouse->name, $warehouse->location, (int) $warehouse->isActive, $warehouse->id]);

        return $warehouse;
    }
}
