<?php

declare(strict_types=1);

namespace App\Repository;

final class MySqlLoginAttemptRepository extends AbstractMySqlRepository implements LoginAttemptRepositoryInterface
{
    public function recordFailure(string $email, string $ip): void
    {
        $this->execute('INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)', [$email, $ip]);
    }

    public function countRecent(string $email, string $ip, int $seconds): int
    {
        $row = $this->fetchRow(
            'SELECT COUNT(*) AS total FROM login_attempts
             WHERE email = ? AND ip_address = ? AND attempted_at >= NOW() - INTERVAL ? SECOND',
            [$email, $ip, $seconds]
        );

        return (int) ($row['total'] ?? 0);
    }

    public function countRecentForIp(string $ip, int $seconds): int
    {
        $row = $this->fetchRow(
            'SELECT COUNT(*) AS total FROM login_attempts
             WHERE ip_address = ? AND attempted_at >= NOW() - INTERVAL ? SECOND',
            [$ip, $seconds]
        );

        return (int) ($row['total'] ?? 0);
    }

    public function clear(string $email, string $ip): void
    {
        $this->execute('DELETE FROM login_attempts WHERE email = ? AND ip_address = ?', [$email, $ip]);
    }

    public function purgeOlderThan(int $seconds): void
    {
        $this->execute('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL ? SECOND', [$seconds]);
    }
}
