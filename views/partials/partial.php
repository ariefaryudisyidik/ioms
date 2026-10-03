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
