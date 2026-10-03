<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\LoginAttemptRepositoryInterface;

/**
 * Brute-force protection for the login form. An email+IP pair is locked after
 * a few failures; a single IP is locked after many failures across emails.
 * Locks lift on their own once the failures age out of the window.
 */
final class LoginThrottle
{
    public const MAX_FAILURES_PER_ACCOUNT = 5;
    public const MAX_FAILURES_PER_IP = 20;
    public const WINDOW_SECONDS = 900;

    public function __construct(private LoginAttemptRepositoryInterface $attempts)
    {
    }

    public function isLocked(string $email, string $ip): bool
    {
        $email = $this->normalize($email);

        return $this->attempts->countRecent($email, $ip, self::WINDOW_SECONDS) >= self::MAX_FAILURES_PER_ACCOUNT
            || $this->attempts->countRecentForIp($ip, self::WINDOW_SECONDS) >= self::MAX_FAILURES_PER_IP;
    }

    public function recordFailure(string $email, string $ip): void
    {
        $this->attempts->purgeOlderThan(self::WINDOW_SECONDS * 4);
        $this->attempts->recordFailure($this->normalize($email), $ip);
    }

    public function reset(string $email, string $ip): void
    {
        $this->attempts->clear($this->normalize($email), $ip);
    }

    private function normalize(string $email): string
    {
        return substr(strtolower(trim($email)), 0, 190);
    }
}
