<?php

declare(strict_types=1);

namespace Backendbase\OpenApiTags;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\PathItem;
use RuntimeException;

use function array_is_list;
use function array_unique;
use function count;
use function in_array;
use function is_file;
use function realpath;
use function sprintf;
use function strtoupper;
use function trim;

function validateFile(string $filename): int
{
    $absolutePath = realpath($filename);
    if ($absolutePath === false || ! is_file($absolutePath)) {
        throw new RuntimeException(sprintf('OpenAPI file does not exist: %s', $filename));
    }

    $document = Reader::readFromYamlFile($absolutePath, OpenApi::class);
    if (! $document->validate()) {
        throw new RuntimeException(sprintf('Invalid OpenAPI document: %s', $filename));
    }

    $declaredNames  = declaredTagNames($document);
    $paths          = $document->paths;
    $operationCount = 0;
    foreach ($paths->getPaths() as $path => $pathItem) {
        $operationCount += validatePath($pathItem, $path, $declaredNames);
    }

    return $operationCount;
}

/** @return list<string> */
function declaredTagNames(OpenApi $document): array
{
    $names = [];
    foreach ($document->tags ?? [] as $tag) {
        $name = $tag->name;
        validateTagName($name);
        $names[] = $name;
    }

    if (count($names) !== count(array_unique($names))) {
        throw new RuntimeException('Duplicate OpenAPI tag declaration.');
    }

    return $names;
}

/** @param list<string> $declaredNames */
function validatePath(PathItem $pathItem, string $path, array $declaredNames): int
{
    $operations = $pathItem->getOperations();
    foreach ($operations as $method => $operation) {
        validateOperation($operation, sprintf('%s %s', strtoupper((string) $method), $path), $declaredNames);
    }

    return count($operations);
}

/** @param list<string> $declaredNames */
function validateOperation(Operation $operation, string $location, array $declaredNames): void
{
    $names = $operation->tags ?? [];
    if ($names === [] || ! array_is_list($names)) {
        throw new RuntimeException(sprintf('%s must define a non-empty tags list.', $location));
    }

    foreach ($names as $name) {
        validateTagReference($name, $location, $declaredNames);
    }
}

/** @param list<string> $declaredNames */
function validateTagReference(string $name, string $location, array $declaredNames): void
{
    validateTagName($name);
    if (! in_array($name, $declaredNames, true)) {
        throw new RuntimeException(sprintf('%s uses undeclared OpenAPI tag: %s', $location, $name));
    }
}

function validateTagName(string $name): void
{
    if ($name === '' || $name !== trim($name)) {
        throw new RuntimeException('OpenAPI tag names must be non-empty and have no surrounding whitespace.');
    }
}
