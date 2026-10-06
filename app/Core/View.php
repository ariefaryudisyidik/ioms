<?php

declare(strict_types=1);

namespace App\Core {

    /**
     * Minimal native PHP template renderer (no template engine dependency).
     */
    final class View
    {
        private static string $viewsPath = __DIR__ . '/../../views';

        public static function setViewsPath(string $path): void
        {
            self::$viewsPath = rtrim($path, '/');
        }

        /**
         * Render a template file into a string.
         *
         * @param string $template dot or slash separated path relative to views/, without extension
         * @param array<string,mixed> $data
         */
        public static function render(string $template, array $data = []): string
        {
            $file = self::resolve($template);

            if (!is_file($file)) {
                error_log("[View] Template not found: {$template} ({$file})");
                return '';
            }

            $renderer = static function (string $__file, array $__data): string {
                extract($__data, EXTR_SKIP);
                ob_start();
                include $__file;

                return (string) ob_get_clean();
            };

            return $renderer($file, $data);
        }

        /**
         * Render directly to output.
         *
         * @param array<string,mixed> $data
         */
        public static function display(string $template, array $data = [], int $status = 200): void
        {
            Response::html(Csrf::inject(self::render($template, $data)), $status);
        }

        private static function resolve(string $template): string
        {
            $relative = str_replace('.', '/', $template);

            return self::$viewsPath . '/' . ltrim($relative, '/') . '.php';
        }
    }
}

// NOTE: PHP applies a non-bracketed `namespace App\Core;` declaration to
// the *entire remainder of the file*, so a bare `function e() {}` placed
// after the class (as in a previous version of this file) would actually
// declare `App\Core\e()`, not a global helper -- and every template
// (plain, non-namespaced .php files under views/) calling `e()`
// unqualified would fatal with "Call to undefined function e()". Using
// bracketed namespace syntax here keeps the helper in the true global
// namespace where templates can reach it.
namespace {
    if (!function_exists('e')) {
        function e(?string $value): string
        {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        }
    }

    if (!function_exists('rupiah')) {
        /** Formats an amount as Indonesian Rupiah without decimals, e.g. "Rp 1.250.000". */
        function rupiah(float|int|string|null $amount): string
        {
            $value = (float) $amount;
            $formatted = number_format(abs(round($value)), 0, ',', '.');

            return ($value < 0 && round($value) != 0 ? '-' : '') . 'Rp ' . $formatted;
        }
    }
}
