<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Synchronizer-token CSRF protection. One random token lives in the session;
 * every state-changing request must echo it back, either as the `_csrf` form
 * field (injected into all POST forms by View::display) or the
 * `X-CSRF-Token` header.
 */
final class Csrf
{
    public const FIELD = '_csrf';
    private const SESSION_KEY = '_csrf_token';
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];
    private const POST_FORM_PATTERN = '#<form\b[^>]*\bmethod\s*=\s*["\']?post["\']?[^>]*>#i';

    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public static function requiresCheck(string $method): bool
    {
        return !in_array(strtoupper($method), self::SAFE_METHODS, true);
    }

    public static function isValid(Request $request): bool
    {
        $supplied = $request->input(self::FIELD) ?? $request->header('X-CSRF-Token');

        return is_string($supplied) && $supplied !== '' && hash_equals(self::token(), $supplied);
    }

    /**
     * Adds the hidden token field to every <form method="post"> in $html.
     */
    public static function inject(string $html): string
    {
        if (stripos($html, '<form') === false) {
            return $html;
        }

        $field = '<input type="hidden" name="' . self::FIELD . '" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';

        return (string) preg_replace_callback(
            self::POST_FORM_PATTERN,
            static fn (array $match): string => $match[0] . $field,
            $html
        );
    }
}
