<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Thin wrapper around PHP native sessions with flash-message and
 * old-input support for backend validation (VAL-01).
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $name = (string) Env::get('SESSION_NAME', 'ioms_session');
            session_name($name);
            session_start();
        }
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
