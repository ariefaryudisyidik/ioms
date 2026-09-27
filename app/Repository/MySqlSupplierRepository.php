<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Supplier;
use PDO;

final class MySqlSupplierRepository implements SupplierRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?Supplier
    {
        $stmt = $this->pdo->prepare('SELECT id, name, contact, address, is_active FROM suppliers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? Supplier::fromRow($row) : null;
    }

    public function all(bool $onlyActive = false): array
    {
        $sql = 'SELECT id, name, contact, address, is_active FROM suppliers'
            . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY name ASC';
        $stmt = $this->pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll() : [];

        return array_map(fn ($r) => Supplier::fromRow($r), $rows);
    }

    public function save(Supplier $supplier): Supplier
    {
        if ($supplier->id === null) {
            $stmt = $this->pdo->prepare('INSERT INTO suppliers (name, contact, address, is_active) VALUES (?,?,?,?)');
            $stmt->execute([$supplier->name, $supplier->contact, $supplier->address, (int) $supplier->isActive]);
            $supplier->id = (int) $this->pdo->lastInsertId();

            return $supplier;
        }

        $stmt = $this->pdo->prepare('UPDATE suppliers SET name=?, contact=?, address=?, is_active=? WHERE id=?');
        $stmt->execute([$supplier->name, $supplier->contact, $supplier->address, (int) $supplier->isActive, $supplier->id]);

        return $supplier;
    }
}
