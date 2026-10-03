<?php

declare(strict_types=1);

namespace App\Repository;

final class InMemoryLoginAttemptRepository implements LoginAttemptRepositoryInterface
{
    /** @var array<int,array{email:string,ip:string,at:int}> */
    private array $attempts = [];

    public function recordFailure(string $email, string $ip): void
    {
        $this->recordFailureAt($email, $ip, time());
    }

    /** Test helper: records an attempt with an explicit timestamp. */
    public function recordFailureAt(string $email, string $ip, int $timestamp): void
    {
        $this->attempts[] = ['email' => $email, 'ip' => $ip, 'at' => $timestamp];
    }

    public function countRecent(string $email, string $ip, int $seconds): int
    {
        return count(array_filter(
            $this->attempts,
            static fn (array $a) => $a['email'] === $email && $a['ip'] === $ip && $a['at'] >= time() - $seconds
        ));
    }

    public function countRecentForIp(string $ip, int $seconds): int
    {
        return count(array_filter(
            $this->attempts,
            static fn (array $a) => $a['ip'] === $ip && $a['at'] >= time() - $seconds
        ));
    }

    public function clear(string $email, string $ip): void
    {
        $this->attempts = array_values(array_filter(
            $this->attempts,
            static fn (array $a) => !($a['email'] === $email && $a['ip'] === $ip)
        ));
    }

    public function purgeOlderThan(int $seconds): void
    {
        $this->attempts = array_values(array_filter(
            $this->attempts,
            static fn (array $a) => $a['at'] >= time() - $seconds
        ));
    }
}
