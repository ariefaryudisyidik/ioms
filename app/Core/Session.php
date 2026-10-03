<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Thin wrapper around PHP native sessions with flash-message and
 * old-input support for backend validation (VAL-01).
 */
final class Session
{
    private const DEFAULT_IDLE_TIMEOUT = 1800;
    private const DEFAULT_ABSOLUTE_TIMEOUT = 28800;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name((string) Env::get('SESSION_NAME', 'ioms_session'));
        if (!headers_sent() && ini_get('session.use_cookies') === '1') {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            // NOSONAR php:S2092: the Secure flag is switched on automatically whenever the request
            // arrives over HTTPS; it must stay off for plain-HTTP local demos or the browser drops the cookie.
            session_set_cookie_params([ // NOSONAR
                'lifetime' => 0,
                'path' => '/',
                'secure' => self::isHttps(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        session_start();
        self::enforceTimeouts();
    }

    public static function isHttps(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';
        $forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';

        return ($https !== '' && $https !== 'off') || $forwarded === 'https';
    }

    /**
     * Drops the session data when it has been idle or alive for too long and
     * issues a fresh session id.
     */
    private static function enforceTimeouts(): void
    {
        $now = time();
        $idle = (int) Env::get('SESSION_IDLE_TIMEOUT', self::DEFAULT_IDLE_TIMEOUT);
        $absolute = (int) Env::get('SESSION_ABSOLUTE_TIMEOUT', self::DEFAULT_ABSOLUTE_TIMEOUT);
        $created = (int) ($_SESSION['_created_at'] ?? $now);
        $lastSeen = (int) ($_SESSION['_last_activity'] ?? $now);

        if ($now - $lastSeen > $idle || $now - $created > $absolute) {
            $_SESSION = [];
            session_regenerate_id(true);
            $created = $now;
        }

        $_SESSION['_created_at'] = $created;
        $_SESSION['_last_activity'] = $now;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();

        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        self::start();

        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    /**
     * Store a one-shot flash message, read once then removed.
     */
    public static function flash(string $key, mixed $value): void
    {
        self::start();
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        self::start();
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);

        return $value;
    }

    public static function hasFlash(string $key): bool
    {
        self::start();

        return isset($_SESSION['_flash'][$key]);
    }

    /**
     * Store the previous request's input so a form can be re-populated
     * after a validation failure.
     *
     * @param array<string,mixed> $input
     */
    public static function setOldInput(array $input): void
    {
        self::start();
        $_SESSION['_old_input'] = $input;
    }

    public static function old(string $key, mixed $default = null): mixed
    {
        self::start();

        return $_SESSION['_old_input'][$key] ?? $default;
    }

    public static function clearOldInput(): void
    {
        self::start();
        unset($_SESSION['_old_input']);
    }

    /**
     * Store validation errors for the next request cycle.
     *
     * @param array<string,string> $errors
     */
    public static function setErrors(array $errors): void
    {
        self::start();
        $_SESSION['_errors'] = $errors;
    }

    /**
     * @return array<string,string>
     */
    public static function getErrors(): array
    {
        self::start();
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);

        return is_array($errors) ? $errors : [];
    }
}
