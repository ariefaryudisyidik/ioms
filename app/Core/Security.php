<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Cross-cutting request protections applied by the front controller:
 * hardening response headers and the CSRF gate for state-changing requests.
 */
final class Security
{
    private const CSP = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

    public static function sendHeaders(): void
    {
        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Content-Security-Policy: ' . self::CSP);

        if (Session::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Rejects state-changing requests that lack a valid CSRF token.
     * Returns true when the request was halted.
     */
    public static function rejectForgedRequest(Request $request): bool
    {
        if (!Csrf::requiresCheck($request->method()) || Csrf::isValid($request)) {
            return false;
        }

        error_log('[Security] CSRF validation failed for ' . $request->method() . ' ' . $request->path());
        if (str_starts_with($request->path(), '/api/')) {
            Response::forbidden('Invalid or missing CSRF token.');
        } else {
            View::display('errors.403', [], 403);
        }

        return true;
    }
}
