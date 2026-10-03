<?php

declare(strict_types=1);

/**
 * auto_prepend_file used by scripts/coverage.sh: records line coverage for the
 * current HTTP request or CLI process and writes it to E2E_COVERAGE_DIR as a
 * .cov file that phpcov merges with the in-process PHPUnit coverage.
 * Does nothing unless E2E_COVERAGE_DIR is set.
 */

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Report\PHP as PhpReport;

(static function (): void {
    $dir = getenv('E2E_COVERAGE_DIR');
    if ($dir === false || $dir === '') {
        return;
    }

    $root = dirname(__DIR__, 2);
    require_once $root . '/vendor/autoload.php';

    $filter = new Filter();
    foreach (['app', 'views', 'public', 'config', 'scripts'] as $source) {
        $filter->includeDirectory($root . '/' . $source);
    }

    $coverage = new CodeCoverage((new Selector())->forLineCoverage($filter), $filter);
    $coverage->start('e2e');

    register_shutdown_function(static function () use ($coverage, $dir): void {
        $coverage->stop();
        (new PhpReport())->process($coverage, $dir . '/' . uniqid('req-', true) . '.cov');
    });
})();
