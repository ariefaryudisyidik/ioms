<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session-backed authentication helper used as a middleware-style guard
 * inside controllers.
 */
final class Auth
{
    private const SESSION_KEY = '_auth_user';

    /**
     * @param array{id:int,name:string,email:string,role:string} $user
     */
    public static function login(array $user): void
    {
        Session::regenerate();
        Session::set(self::SESSION_KEY, [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ]);
    }

    public static function logout(): void
    {
        Session::remove(self::SESSION_KEY);
        Session::destroy();
    }

    public static function check(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    /**
     * @return array{id:int,name:string,email:string,role:string}|null
     */
    public static function user(): ?array
    {
        return Session::get(self::SESSION_KEY);
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user['id'] ?? null;
    }

    public static function role(): ?string
    {
        $user = self::user();

        return $user['role'] ?? null;
    }

    public static function hasRole(string ...$roles): bool
    {
        $role = self::role();

        return $role !== null && in_array($role, $roles, true);
    }

    /**
     * Redirects to the login page (HTML flow) when the user is not
     * authenticated. Returns true when the request was halted.
     */
    public static function requireLogin(string $loginUrl = '/login'): bool
    {
        if (!self::check()) {
            Response::redirect($loginUrl);

            return true;
        }

        return false;
    }

    /**
     * Enforces role membership for HTML flows: not logged in -> redirect to
     * login; logged in but wrong role -> render a real 403 page (ERR-01).
     * Returns true when the request was halted.
     */
    public static function requireRole(string ...$roles): bool
    {
        if (self::requireLogin()) {
            return true;
        }

        if (!self::hasRole(...$roles)) {
            View::display('errors/403', [], 403);

            return true;
        }

        return false;
    }

    /**
     * JSON/API variant: emits proper status codes instead of redirecting.
     * Returns true when the request was halted.
     */
    public static function requireLoginApi(): bool
    {
        if (!self::check()) {
            Response::unauthorized();

            return true;
        }

        return false;
    }

    public static function requireRoleApi(string ...$roles): bool
    {
        if (self::requireLoginApi()) {
            return true;
        }

        if (!self::hasRole(...$roles)) {
            Response::forbidden();

            return true;
        }

        return false;
    }
}
