<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use PDO;

final class MySqlUserRepository implements UserRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, password_hash, role, is_active, created_at, updated_at FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? User::fromRow($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, password_hash, role, is_active, created_at, updated_at FROM users WHERE email = ?'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        return $row ? User::fromRow($row) : null;
    }

    public function all(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name, email, password_hash, role, is_active, created_at, updated_at FROM users ORDER BY id DESC'
        );
        $rows = $stmt ? $stmt->fetchAll() : [];

        return array_map(fn ($r) => User::fromRow($r), $rows);
    }

    public function save(User $user): User
    {
        if ($user->id === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?,?,?,?,?)'
            );
            $stmt->execute([$user->name, $user->email, $user->passwordHash, $user->role, (int) $user->isActive]);
            $user->id = (int) $this->pdo->lastInsertId();

            return $user;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE users SET name=?, email=?, password_hash=?, role=?, is_active=? WHERE id=?'
        );
        $stmt->execute([$user->name, $user->email, $user->passwordHash, $user->role, (int) $user->isActive, $user->id]);

        return $user;
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
            $stmt->execute([$email, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
            $stmt->execute([$email]);
        }

        return ((int) $stmt->fetchColumn()) > 0;
    }
}
