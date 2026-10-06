<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;

/**
 * Shared CRUD flow for simple admin resources. Concrete controllers declare
 * only the resource specifics through the constants and service().
 */
abstract class CrudController extends Controller
{
    protected const VIEW = '';
    protected const BASE_URL = '';
    protected const ENTITY_KEY = '';
    protected const LIST_KEY = '';
    protected const REMOVE_METHOD = 'deactivate';
    protected const CREATE_DATA = [];
    /** @var list<string> Roles allowed to list; empty means any logged-in user. */
    protected const INDEX_ROLES = [];
    /** @var list<string> */
    protected const WRITE_ROLES = ['Admin'];
    /** @var list<string> */
    protected const REMOVE_ROLES = ['Admin'];

    abstract protected function service(): object;

    protected function call(string $method, mixed ...$args): mixed
    {
        return $this->service()->{$method}(...$args);
    }

    protected function denyIndex(): bool
    {
        if (static::INDEX_ROLES === []) {
            return Auth::requireLogin();
        }

        return Auth::requireRole(...static::INDEX_ROLES);
    }

    protected function renderIndex(Request $request): void
    {
        $status = (string) $request->query('status', '');
        $filters = [
            'search' => mb_substr(trim((string) $request->query('search', '')), 0, 100),
            'status' => in_array($status, ['active', 'inactive'], true) ? $status : '',
            'sort' => $this->call('normalizeSort', (string) $request->query('sort', '')),
        ];
        $this->render(static::VIEW . '.index', [
            static::LIST_KEY => $this->call('list', $filters),
            'filters' => $filters,
        ]);
    }

    public function index(Request $request): void
    {
        if ($this->denyIndex()) {
            return;
        }
        $this->renderIndex($request);
    }

    public function create(): void
    {
        if (Auth::requireRole(...static::WRITE_ROLES)) {
            return;
        }
        $this->render(static::VIEW . '.create', static::CREATE_DATA);
    }

    public function store(Request $request): void
    {
        if (Auth::requireRole(...static::WRITE_ROLES)) {
            return;
        }
        $this->handle(function () use ($request) {
            $this->call('create', $request->all());
            $this->redirect(static::BASE_URL);
        }, false, static::BASE_URL . '/create');
    }

    public function edit(array $params): void
    {
        if (Auth::requireRole(...static::WRITE_ROLES)) {
            return;
        }
        $entity = $this->call('find', (int) $params['id']);
        if ($entity === null) {
            $this->render('errors.404', [], 404);

            return;
        }
        $this->render(static::VIEW . '.edit', [static::ENTITY_KEY => $entity]);
    }

    public function update(Request $request, array $params): void
    {
        if (Auth::requireRole(...static::WRITE_ROLES)) {
            return;
        }
        $id = (int) $params['id'];
        $this->handle(function () use ($request, $id) {
            $this->call('update', $id, $request->all());
            $this->redirect(static::BASE_URL);
        }, false, static::BASE_URL . "/{$id}/edit");
    }

    /**
     * Removes (deactivates or deletes) the entity identified by $params['id'].
     */
    protected function remove(array $params): void
    {
        if (Auth::requireRole(...static::REMOVE_ROLES)) {
            return;
        }
        $id = (int) $params['id'];
        $this->handle(function () use ($id) {
            $this->call(static::REMOVE_METHOD, $id);
            $this->redirect(static::BASE_URL);
        }, false, static::BASE_URL);
    }
}
