<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Search, status filter and column sort for small in-memory lists (master data).
 * A sort value is "<column>_<asc|desc>", e.g. "name_desc" or "purchase_price_asc".
 */
final class ListQuery
{
    public const ASC = 'asc';
    public const DESC = 'desc';

    /**
     * @param list<object> $items
     * @param array{search?:string,status?:string,sort?:string} $params
     * @param array<string, callable(object):mixed> $columns accessors keyed by column name
     * @param list<string> $searchColumns columns matched (case-insensitive substring) by the search text
     * @param string|null $statusColumn boolean column filtered by status "active" or "inactive"
     * @return list<object>
     */
    public static function apply(
        array $items,
        array $params,
        array $columns,
        array $searchColumns,
        ?string $statusColumn = null,
        string $defaultSort = 'name_asc'
    ): array {
        $items = self::filter($items, $params, $columns, $searchColumns, $statusColumn);
        [$column, $direction] = self::parseSort((string) ($params['sort'] ?? ''), array_keys($columns), $defaultSort);
        $factor = $direction === self::DESC ? -1 : 1;
        usort($items, static fn (object $a, object $b): int => $factor * self::compare($columns[$column]($a), $columns[$column]($b)));

        return $items;
    }

    /**
     * Valid sort value for an order list (number, party, date or status); the legacy "asc"/"desc"
     * means by date. Unknown input becomes date_desc.
     */
    public static function normalizeOrderSort(string $sort): string
    {
        $sort = in_array($sort, [self::ASC, self::DESC], true) ? 'date_' . $sort : $sort;
        [$column, $direction] = self::parseSort($sort, ['number', 'party', 'date', 'status'], 'date_desc');

        return $column . '_' . $direction;
    }

    /**
     * Splits a sort value into [column, direction]; unknown columns fall back to the default.
     *
     * @param list<string> $allowed
     * @return array{0:string,1:string}
     */
    public static function parseSort(string $sort, array $allowed, string $default): array
    {
        $split = static function (string $value): array {
            $cut = (int) strrpos($value, '_');

            return [substr($value, 0, $cut), substr($value, $cut + 1)];
        };
        [$column, $direction] = $split($sort);
        if (!in_array($column, $allowed, true) || !in_array($direction, [self::ASC, self::DESC], true)) {
            [$column, $direction] = $split($default);
        }

        return [$column, $direction];
    }

    /**
     * @param list<object> $items
     * @param array{search?:string,status?:string} $params
     * @param array<string, callable(object):mixed> $columns
     * @param list<string> $searchColumns
     * @return list<object>
     */
    private static function filter(array $items, array $params, array $columns, array $searchColumns, ?string $statusColumn): array
    {
        $needle = mb_strtolower(trim((string) ($params['search'] ?? '')));
        $status = (string) ($params['status'] ?? '');

        return array_values(array_filter(
            $items,
            static fn (object $item): bool => self::matchesStatus($item, $status, $columns, $statusColumn)
                && self::matchesSearch($item, $needle, $columns, $searchColumns)
        ));
    }

    /** @param array<string, callable(object):mixed> $columns */
    private static function matchesStatus(object $item, string $status, array $columns, ?string $statusColumn): bool
    {
        if ($statusColumn === null || !in_array($status, ['active', 'inactive'], true)) {
            return true;
        }

        return (bool) $columns[$statusColumn]($item) === ($status === 'active');
    }

    /**
     * @param array<string, callable(object):mixed> $columns
     * @param list<string> $searchColumns
     */
    private static function matchesSearch(object $item, string $needle, array $columns, array $searchColumns): bool
    {
        if ($needle === '') {
            return true;
        }
        foreach ($searchColumns as $column) {
            if (str_contains(mb_strtolower((string) $columns[$column]($item)), $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function compare(mixed $a, mixed $b): int
    {
        if (is_numeric($a) && is_numeric($b)) {
            return $a <=> $b;
        }

        return strnatcasecmp((string) $a, (string) $b);
    }
}
