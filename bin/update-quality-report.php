<?php

declare(strict_types=1);

namespace Backendbase\QualityReport;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

use function array_shift;
use function array_slice;
use function basename;
use function count;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function fwrite;
use function implode;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function number_format;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function sprintf;
use function str_ends_with;
use function str_starts_with;

use const STDERR;
use const STDOUT;

const REPORTS          = ['resources/docs/code-quality-and-maintainability-report.html'];
const REQUIRED_METRICS = [
    'tests',
    'assertions',
    'covered-lines',
    'total-lines',
    'line-coverage-percent',
    'covered-methods',
    'total-methods',
    'method-coverage-percent',
    'phpcs-files',
];

try {
    $root      = dirname(__DIR__);
    $arguments = array_slice(commandArguments(), 1);
    $checkOnly = ($arguments[0] ?? '') === '--check';
    if ($checkOnly) {
        array_shift($arguments);
    }

    $coverageFile = $root . '/' . ($arguments[0] ?? 'clover.xml');
    $junitFile    = $root . '/' . ($arguments[1] ?? 'artifacts/junit.xml');
    $metrics      = reportMetrics($root, $coverageFile, $junitFile);
    $changed      = [];

    $reports = REPORTS;
    foreach ($reports as $report) {
        $reportFile = $root . '/' . $report;
        if (! updateReport($reportFile, $metrics, $checkOnly, true)) {
            continue;
        }

        $changed[] = $report;
    }

    if ($checkOnly && $changed !== []) {
        throw new RuntimeException(
            'Quality report metrics are stale: ' . implode(', ', $changed) . '. Run composer reports:update.',
        );
    }

    $verb = $checkOnly ? 'verified' : 'updated';
    fwrite(STDOUT, sprintf('Quality report metrics %s. Reports checked: %d.', $verb, count($reports)) . "\n");
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

/** @return array<string, string> */
function reportMetrics(string $root, string $coverageFile, string $junitFile): array
{
    $coverage = projectMetrics($coverageFile, '/coverage/project/metrics');
    $junit    = projectMetrics($junitFile, '/testsuites/testsuite[1]');
    $total    = integerMetric($coverage, 'statements');
    $covered  = integerMetric($coverage, 'coveredstatements');

    return [
        'tests' => formattedMetric($junit, 'tests'),
        'assertions' => formattedMetric($junit, 'assertions'),
        'covered-lines' => number_format($covered),
        'total-lines' => number_format($total),
        'line-coverage-percent' => percentage($covered, $total),
        'covered-methods' => formattedMetric($coverage, 'coveredmethods'),
        'total-methods' => formattedMetric($coverage, 'methods'),
        'method-coverage-percent' => percentage(
            integerMetric($coverage, 'coveredmethods'),
            integerMetric($coverage, 'methods'),
        ),
        'phpcs-files' => number_format(countPhpFiles($root, ['src', 'tests', 'config', 'public', 'bin', 'resources/database'])),
        'source-files' => number_format(countPhpFiles($root, ['src'])),
        'test-files' => number_format(countPhpFiles($root, ['tests'])),
        'html-files' => number_format(countFiles($root . '/resources/docs', '.html')),
        'typed-configuration-types' => number_format(countTypedConfigurationTypes($root)),
    ];
}

/** @return list<string> */
function commandArguments(): array
{
    $arguments = $_SERVER['argv'] ?? [];
    if (! is_array($arguments)) {
        throw new RuntimeException('Command arguments are invalid.');
    }

    $validated = [];
    foreach ($arguments as $argument) {
        if (! is_string($argument)) {
            throw new RuntimeException('Command arguments must be strings.');
        }

        $validated[] = $argument;
    }

    return $validated;
}

/** @return array<string, string> */
function projectMetrics(string $file, string $query): array
{
    if (! is_file($file)) {
        throw new RuntimeException(sprintf('Required report does not exist: %s', $file));
    }

    $document = new DOMDocument();
    if (! $document->load($file)) {
        throw new RuntimeException(sprintf('Report XML is invalid: %s', $file));
    }

    $nodes = (new DOMXPath($document))->query($query);
    if ($nodes === false) {
        throw new RuntimeException(sprintf('Report metrics query is invalid: %s', $file));
    }

    $element = $nodes->item(0);
    if (! $element instanceof DOMElement) {
        throw new RuntimeException(sprintf('Report metrics are missing: %s', $file));
    }

    $metrics = [];
    foreach ($element->attributes as $attribute) {
        $metrics[$attribute->name] = $attribute->value;
    }

    return $metrics;
}

/** @param array<string, string> $metrics */
function formattedMetric(array $metrics, string $name): string
{
    return number_format(integerMetric($metrics, $name));
}

/** @param array<string, string> $metrics */
function integerMetric(array $metrics, string $name): int
{
    $value = $metrics[$name] ?? null;
    if ($value === null || preg_match('/^\d+$/', $value) !== 1) {
        throw new RuntimeException(sprintf('Report metric is missing or invalid: %s', $name));
    }

    return (int) $value;
}

function percentage(int $covered, int $total): string
{
    if ($total === 0) {
        throw new RuntimeException('Coverage report has no executable lines.');
    }

    return number_format($covered / $total * 100, 2) . '%';
}

/** @param list<string> $directories */
function countPhpFiles(string $root, array $directories): int
{
    $count = 0;
    foreach ($directories as $directory) {
        $count += countFiles($root . '/' . $directory, '.php');
    }

    return $count;
}

function countFiles(string $directory, string $suffix): int
{
    if (! is_dir($directory)) {
        throw new RuntimeException(sprintf('Inventory directory does not exist: %s', $directory));
    }

    $count    = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    foreach ($iterator as $file) {
        if (! ($file instanceof SplFileInfo) || ! $file->isFile() || ! str_ends_with($file->getFilename(), $suffix)) {
            continue;
        }

        ++$count;
    }

    return $count;
}

function countTypedConfigurationTypes(string $root): int
{
    $directories = [
        $root . '/src/Backendbase/Infrastructure/Configuration',
        $root . '/src/Backendbase/Shared/Configuration',
    ];
    $count       = 0;

    foreach ($directories as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            if (str_starts_with(basename($file->getFilename()), 'Validated')) {
                continue;
            }

            ++$count;
        }
    }

    return $count;
}

/** @param array<string, string> $metrics */
function updateReport(string $file, array $metrics, bool $checkOnly, bool $requireAllMetrics): bool
{
    $contents = file_get_contents($file);
    if ($contents === false) {
        throw new RuntimeException(sprintf('Cannot read quality report: %s', $file));
    }

    $updated = $contents;
    foreach ($metrics as $name => $value) {
        $pattern          = '~(<span data-quality-metric="' . preg_quote($name, '~') . '">)[^<]*(</span>)~';
        $replacementCount = 0;
        $updated          = preg_replace($pattern, '${1}' . $value . '${2}', $updated, -1, $replacementCount);
        if ($updated === null) {
            throw new RuntimeException(sprintf('Cannot update metric %s in %s', $name, $file));
        }

        if ($requireAllMetrics && in_array($name, REQUIRED_METRICS, true) && $replacementCount === 0) {
            throw new RuntimeException(sprintf('Required report metric marker is missing: %s in %s', $name, $file));
        }
    }

    if ($updated === $contents) {
        return false;
    }

    if (! $checkOnly && file_put_contents($file, $updated) === false) {
        throw new RuntimeException(sprintf('Cannot write quality report: %s', $file));
    }

    return true;
}
