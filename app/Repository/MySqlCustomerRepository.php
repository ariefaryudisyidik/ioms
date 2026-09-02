<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;
use PDO;

final class MySqlCustomerRepository implements CustomerRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?Customer
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? Customer::fromRow($row) : null;
    }

    public function all(bool $onlyActive = false): array
    {
        $sql = 'SELECT * FROM customers' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY name ASC';
        $stmt = $this->pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll() : [];

        return array_map(fn ($r) => Customer::fromRow($r), $rows);
    }

    public function save(Customer $customer): Customer
    {
        if ($customer->id === null) {
            $stmt = $this->pdo->prepare('INSERT INTO customers (name, contact, address, is_active) VALUES (?,?,?,?)');
            $stmt->execute([$customer->name, $customer->contact, $customer->address, (int) $customer->isActive]);
            $customer->id = (int) $this->pdo->lastInsertId();

            return $customer;
        }

        $stmt = $this->pdo->prepare('UPDATE customers SET name=?, contact=?, address=?, is_active=? WHERE id=?');
        $stmt->execute([$customer->name, $customer->contact, $customer->address, (int) $customer->isActive, $customer->id]);

        return $customer;
    }
}
