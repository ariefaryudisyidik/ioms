<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;

final class DateRules
{
    /**
     * True for a real calendar date written as Y-m-d (rejects 2026-02-31, 31-12-2026, etc.).
     */
    public static function isValidYmd(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        $hasParseIssues = $errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);

        return $date !== false && !$hasParseIssues;
    }
}
