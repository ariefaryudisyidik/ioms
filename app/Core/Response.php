<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Small helper to emit HTML / JSON / redirect responses with status codes.
 */
final class Response
{
    public static function html(string $content, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $content;
    }

    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header('Location: ' . $url);
    }

    public static function csv(string $filename, string $content): void
    {
        http_response_code(200);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $content;
    }

    public static function notFound(string $message = 'Not Found'): void
    {
        self::json(['error' => $message], 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::json(['error' => $message], 401);
    }

    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::json(['error' => $message], 403);
    }
}
