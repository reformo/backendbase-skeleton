<?php

declare(strict_types=1);

namespace Backendbase\Coverage;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

use function fwrite;
use function is_file;
use function sprintf;

use const STDERR;
use const STDOUT;

const REQUIRED_LINE_COVERAGE_PERCENTAGE = 100.0;

try {
    $coverageFile = $argv[1] ?? '';
    if ($coverageFile === '' || ! is_file($coverageFile)) {
        throw new RuntimeException('Provide an existing Clover coverage file.');
    }

    $document = new DOMDocument();
    if (! $document->load($coverageFile)) {
        throw new RuntimeException('The Clover coverage file is invalid.');
    }

    $metricNodes = new DOMXPath($document)->query('/coverage/project/metrics');
    if ($metricNodes === false) {
        throw new RuntimeException('The Clover project metrics query is invalid.');
    }

    $metrics = $metricNodes->item(0);
    if (! $metrics instanceof DOMElement) {
        throw new RuntimeException('The Clover coverage file has no project metrics.');
    }

    $coveredLines = (int) $metrics->getAttribute('coveredstatements');
    $totalLines   = (int) $metrics->getAttribute('statements');
    if ($totalLines === 0) {
        throw new RuntimeException('The Clover coverage file has no executable lines.');
    }

    $coveragePercentage = $coveredLines / $totalLines * 100;
    $message            = sprintf(
        'Executable line coverage: %d/%d (%.2f%%). Required: %.2f%%.',
        $coveredLines,
        $totalLines,
        $coveragePercentage,
        REQUIRED_LINE_COVERAGE_PERCENTAGE,
    );

    if ($coveredLines !== $totalLines) {
        throw new RuntimeException($message);
    }

    fwrite(STDOUT, $message . "\n");
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
