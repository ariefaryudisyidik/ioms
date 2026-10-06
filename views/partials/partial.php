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
