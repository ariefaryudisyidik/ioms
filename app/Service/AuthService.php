<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepositoryInterface;

final class AuthService
{
    /** Verified against when the account is unknown so response time does not reveal which emails exist. */
    private const DUMMY_HASH = '$2y$12$uGT8775vor2sSjPnHWfT0OWRvCX3mN/pCYRxo38Cl/g/U8rQrT41O';

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
        $passwordMatches = password_verify($password, $user->passwordHash ?? self::DUMMY_HASH);

        return $user !== null && $user->isActive && $passwordMatches ? $user : null;
    }
}
