<?php

declare(strict_types=1);

namespace App\Repository;

interface LoginAttemptRepositoryInterface
{
    public function recordFailure(string $email, string $ip): void;

    /** Failed attempts for this email from this IP within the last $seconds. */
    public function countRecent(string $email, string $ip, int $seconds): int;

    /** Failed attempts from this IP (any email) within the last $seconds. */
    public function countRecentForIp(string $ip, int $seconds): int;

    public function clear(string $email, string $ip): void;

    /** Removes attempts older than $seconds. */
    public function purgeOlderThan(int $seconds): void;
}
