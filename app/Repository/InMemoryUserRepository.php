<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var User[] */
    private array $users = [];
    private int $nextId = 1;

    public function findById(int $id): ?User
    {
        foreach ($this->users as $u) {
            if ($u->id === $id) {
                return $u;
            }
        }

        return null;
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $u) {
            if ($u->email === $email) {
                return $u;
            }
        }

        return null;
    }

    public function all(): array
    {
        return array_values($this->users);
    }

    public function save(User $user): User
    {
        if ($user->id === null) {
            $user->id = $this->nextId++;
        }
        $this->users[$user->id] = $user;

        return $user;
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        foreach ($this->users as $u) {
            if ($u->email === $email && $u->id !== $excludeId) {
                return true;
            }
        }

        return false;
    }
}
