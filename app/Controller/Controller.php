<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
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

    protected function withOldInputOnError(Request $request, string $backUrl, ValidationException $e): void
    {
        Session::setOldInput($request->all());
        Session::setErrors($e->errors());
        $this->redirect($backUrl);
    }

    /**
     * Wraps a controller action body, translating domain exceptions into
     * the ERR-01 contract. $isApi selects JSON vs HTML/redirect handling.
     */
    protected function handle(callable $action, bool $isApi, string $fallbackUrl = '/'): mixed
    {
        try {
            return $action();
        } catch (ValidationException $e) {
            if ($isApi) {
                $this->json(['error' => 'Validation failed.', 'errors' => $e->errors()], 422);

                return null;
            }
            Session::setErrors($e->errors());
            $this->redirect($fallbackUrl);

            return null;
        } catch (AuthorizationException $e) {
            error_log('[Authorization] ' . $e->getMessage());
            if ($isApi) {
                Response::forbidden($e->getMessage());

                return null;
            }
            Response::html('<h1>403 Forbidden</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>', 403);

            return null;
        } catch (InvalidStatusTransitionException | InsufficientStockException $e) {
            error_log('[Domain] ' . $e->getMessage());
            if ($isApi) {
                $this->json(['error' => $e->getMessage()], 409);

                return null;
            }
            Session::flash('error', $e->getMessage());
            $this->redirect($fallbackUrl);

            return null;
        } catch (Throwable $e) {
            error_log('[Unhandled] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            if ($isApi) {
                $this->json(['error' => 'An unexpected error occurred.'], 500);

                return null;
            }
            Response::html('<h1>500 Internal Server Error</h1><p>An unexpected error occurred.</p>', 500);

            return null;
        }
    }
}
