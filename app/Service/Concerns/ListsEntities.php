<?php

declare(strict_types=1);

namespace App\Service\Concerns;

use App\Service\ListQuery;

/**
 * Search, status filter and header sorting for a service's master data list. The service names its
 * sortable/searchable columns; this trait applies the query and keeps the sort value valid.
 */
trait ListsEntities
{
    /** @return list<object> */
    abstract public function all(): array;

    /** @return array<string, callable(object):mixed> accessors keyed by column name */
    abstract protected function listColumns(): array;

    /** @return list<string> columns the search text is matched against */
    abstract protected function searchColumns(): array;

    /** Boolean column the status filter applies to, or null when the list has no status. */
    protected function statusColumn(): ?string
    {
        return null;
    }

    /**
     * @param array{search?:string,status?:string,sort?:string} $params
     * @return list<object>
     */
    public function list(array $params): array
    {
        return ListQuery::apply($this->all(), $params, $this->listColumns(), $this->searchColumns(), $this->statusColumn());
    }

    /** Returns a valid "<column>_<asc|desc>" sort value; unknown input becomes name_asc. */
    public function normalizeSort(string $sort): string
    {
        [$column, $direction] = ListQuery::parseSort($sort, array_keys($this->listColumns()), 'name_asc');

        return $column . '_' . $direction;
    }
}
