<?php

declare(strict_types=1);

namespace Tests\Unit;

/**
 * Lets App\Core\Session work inside the CLI test runner (no cookies/headers).
 */
trait SessionSupport
{
    protected function startTestSession(): void
    {
        ini_set('session.use_cookies', '0');
        ini_set('session.cache_limiter', '');
        ini_set('error_log', (string) tempnam(sys_get_temp_dir(), 'unit-errors'));
        $this->stopTestSession();
        session_id('unit' . bin2hex(random_bytes(8)));
    }

    protected function stopTestSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        http_response_code(200);
    }

    /**
     * Runs $callback and returns everything it printed.
     */
    protected function capture(callable $callback): string
    {
        ob_start();
        try {
            $callback();
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }
}
