<?php

declare(strict_types=1);

use function Backendbase\OpenApiTags\validateFile;

$projectRoot = dirname(__DIR__);
chdir($projectRoot);
require 'vendor/autoload.php';
require 'bin/openapi-tags.php';

try {
    $filenames = array_slice($argv ?? [], 1);
    if ($filenames === []) {
        throw new RuntimeException('Usage: check-openapi-tags.php <OpenAPI YAML file> [...]');
    }

    foreach ($filenames as $filename) {
        $operationCount = validateFile($filename);
        fwrite(STDOUT, sprintf('OpenAPI tags passed for %s (%d operations).', $filename, $operationCount) . "\n");
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
