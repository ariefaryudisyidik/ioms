<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepositoryInterface;

final class AuthService
{
    public function __construct(private UserRepositoryInterface $users)
    {
    }

    /**
     * Verify credentials for a login attempt.
     * Returns the User on success, or null on failure (wrong email/password
     * or the account is inactive).
     */
    public function attempt(string $email, string $password): ?User
    {
        $user = $this->users->findByEmail($email);
        if ($user === null || !$user->isActive) {
            return null;
        }

        if (!password_verify($password, $user->passwordHash)) {
            return null;
        }

        return $user;
    }
}
