<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Service\Exception\AuthorizationException;
use App\Service\Exception\InsufficientStockException;
use App\Service\Exception\InvalidStatusTransitionException;
use App\Service\Exception\ValidationException;
use PDO;
use Throwable;

/**
 * Base controller: shared helpers for view rendering, DB access, and the
 * ERR-01 error handling contract (401 -> redirect/json, 403, 404, generic
 * message + server-side logging, no stack traces to the client).
 */
abstract class Controller
{
    protected function pdo(): PDO
    {
        return Database::connection();
    }

    protected function render(string $template, array $data = [], int $status = 200): void
    {
        $data['auth_user'] = Auth::user();
        $data['errors'] = $data['errors'] ?? Session::getErrors();
        View::display($template, $data, $status);
    }

    protected function redirect(string $url): void
    {
        Response::redirect($url);
    }

    protected function json(array $data, int $status = 200): void
    {
        Response::json($data, $status);
    }

    /**
     * Wraps a controller action body, translating domain exceptions into
     * the ERR-01 contract. $isApi selects JSON vs HTML/redirect handling.
     */
    protected function handle(callable $action, bool $isApi, string $fallbackUrl = '/'): mixed
    {
        try {
            return $action();
        } catch (Throwable $e) {
            $this->respondToFailure($e, $isApi, $fallbackUrl);

            return null;
        }
    }

    private function respondToFailure(Throwable $e, bool $isApi, string $fallbackUrl): void
    {
        if ($e instanceof ValidationException) {
            $this->respondValidation($e, $isApi, $fallbackUrl);
        } elseif ($e instanceof AuthorizationException) {
            $this->respondForbidden($e, $isApi);
        } elseif ($e instanceof InvalidStatusTransitionException || $e instanceof InsufficientStockException) {
            $this->respondConflict($e, $isApi, $fallbackUrl);
        } else {
            $this->respondUnexpected($e, $isApi);
        }
    }

    private function respondValidation(ValidationException $e, bool $isApi, string $fallbackUrl): void
    {
        if ($isApi) {
            $this->json(['error' => 'Validation failed.', 'errors' => $e->errors()], 422);

            return;
        }
        Session::setErrors($e->errors());
        $this->redirect($fallbackUrl);
    }

    private function respondForbidden(AuthorizationException $e, bool $isApi): void
    {
        error_log('[Authorization] ' . $e->getMessage());
        if ($isApi) {
            Response::forbidden($e->getMessage());

            return;
        }
        Response::html('<h1>403 Forbidden</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>', 403);
    }

    private function respondConflict(Throwable $e, bool $isApi, string $fallbackUrl): void
    {
        error_log('[Domain] ' . $e->getMessage());
        if ($isApi) {
            $this->json(['error' => $e->getMessage()], 409);

            return;
        }
        Session::flash('error', $e->getMessage());
        $this->redirect($fallbackUrl);
    }

    private function respondUnexpected(Throwable $e, bool $isApi): void
    {
        error_log('[Unhandled] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        if ($isApi) {
            $this->json(['error' => 'An unexpected error occurred.'], 500);

            return;
        }
        Response::html('<h1>500 Internal Server Error</h1><p>An unexpected error occurred.</p>', 500);
    }
}
