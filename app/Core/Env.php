<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal dependency-free .env loader.
 * Parses KEY=VALUE lines (supports quoted values and # comments) into
 * $_ENV / $_SERVER / getenv().
 */
final class Env
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        if (!is_file($path) || !is_readable($path)) {
            self::$loaded = true;
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            self::$loaded = true;
            return;
        }

        foreach ($lines as $line) {
            $pair = self::parseLine($line);
            if ($pair === null) {
                continue;
            }

            [$name, $value] = $pair;
            if (getenv($name) === false) {
                putenv($name . '=' . $value);
            }
            $_ENV[$name] = $_ENV[$name] ?? $value;
            $_SERVER[$name] = $_SERVER[$name] ?? $value;
        }

        self::$loaded = true;
    }

    /**
     * @return array{0:string,1:string}|null null for blank, comment, or malformed lines
     */
    private static function parseLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            return null;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);

        return $name === '' ? null : [$name, self::stripQuotes(trim($value))];
    }

    private static function stripQuotes(string $value): string
    {
        if (strlen($value) < 2) {
            return $value;
        }

        $first = $value[0];
        $isQuoted = ($first === '"' || $first === "'") && $value[strlen($value) - 1] === $first;

        return $isQuoted ? substr($value, 1, -1) : $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}
