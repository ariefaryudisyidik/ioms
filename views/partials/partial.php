<?php
/**
 * Render a partial from views/partials with an explicit set of variables.
 */
if (!function_exists('partial')) {
    /**
     * @param array<string,mixed> $vars
     */
    function partial(string $name, array $vars = []): void
    {
        (static function (string $__file, array $__vars): void {
            extract($__vars, EXTR_SKIP);
            include $__file;
        })(__DIR__ . '/' . $name . '.php', $vars);
    }
}

if (!function_exists('icon')) {
    /**
     * Inline a Lucide SVG (public/assets/icons) so it inherits currentColor. Name must be kebab-case.
     */
    function icon(string $name): string
    {
        $file = __DIR__ . '/../../public/assets/icons/' . $name . '.svg';
        if (!preg_match('/^[a-z0-9-]+$/', $name) || !is_file($file)) {
            return '';
        }
        $svg = (string) file_get_contents($file);
        $svg = preg_replace('/<!--.*?-->\s*/s', '', $svg) ?? $svg;
        $svg = preg_replace_callback(
            '/<svg[^>]*>/s',
            static fn (array $m): string => '<svg class="icon" aria-hidden="true" focusable="false"'
                . preg_replace('/\s(class|width|height)="[^"]*"/', '', substr($m[0], 4)),
            $svg,
            1
        ) ?? $svg;

        return trim($svg);
    }
}

if (!function_exists('roleLabel')) {
    /**
     * Display name of a role ("WarehouseStaff" -> "Warehouse Staff"); the stored value is unchanged.
     */
    function roleLabel(string $role): string
    {
        return (string) preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $role);
    }
}

if (!function_exists('sortDirection')) {
    /**
     * Direction ("asc" or "desc") a list is sorted in for one column, or "" when the column is not the sorted one.
     * A sort value is "<column>_<asc|desc>", e.g. "purchase_price_desc".
     */
    function sortDirection(string $sort, string $column): string
    {
        foreach (['asc', 'desc'] as $direction) {
            if ($sort === $column . '_' . $direction) {
                return $direction;
            }
        }

        return '';
    }
}

if (!function_exists('sortUrl')) {
    /**
     * Link of a sortable column header: sorts ascending first, then flips direction on every click.
     * Other filters stay, the page resets to 1.
     *
     * @param array<string,mixed> $query current filters (search, status, ...)
     */
    function sortUrl(string $baseUrl, array $query, string $column, string $sort): string
    {
        $next = sortDirection($sort, $column) === 'asc' ? 'desc' : 'asc';
        $params = array_filter(
            array_merge($query, ['sort' => $column . '_' . $next]),
            static fn ($value) => $value !== '' && $value !== null
        );

        return $baseUrl . '?' . http_build_query($params);
    }
}

if (!function_exists('statusLabel')) {
    /**
     * Display text of an order status ("PartiallyReceived" -> "Partially Received", "PendingApproval" ->
     * "Pending Approval"); the stored value and the CSS class stay unchanged.
     */
    function statusLabel(string $status): string
    {
        return (string) preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $status);
    }
}
