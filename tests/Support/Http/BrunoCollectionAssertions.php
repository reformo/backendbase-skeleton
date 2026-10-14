<?php

declare(strict_types=1);

namespace Tests\Support\Http;

use cebe\openapi\spec\Operation;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Paths;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Responses;
use cebe\openapi\spec\Schema;
use PHPUnit\Framework\Assert;
use Symfony\Component\Yaml\Yaml;

use function glob;
use function implode;
use function preg_quote;
use function preg_replace;
use function sort;
use function str_contains;
use function str_replace;
use function strtolower;

final class BrunoCollectionAssertions
{
    /**
     * @param Paths<PathItem> $paths
     *
     * @return list<string>
     */
    public static function operations(string $slug, Paths $paths): array
    {
        $files = glob('resources/bruno/' . $slug . '/*/*.yml');
        Assert::assertIsArray($files);
        $operations = [];
        foreach ($files as $file) {
            $request = Yaml::parseFile($file);
            Assert::assertIsArray($request);
            $operation = self::requestOperation($request, $paths);
            if ($operation === null) {
                continue;
            }

            $operations[] = $operation;
        }

        sort($operations);

        return $operations;
    }

    public static function normalize(string $path): string
    {
        return preg_replace('/\{\{?[^}]+\}\}?/', '{}', $path) ?? $path;
    }

    /**
     * @param array<string, mixed> $request
     * @param Paths<PathItem>      $paths
     */
    private static function requestOperation(array $request, Paths $paths): string|null
    {
        if (! isset($request['http'])) {
            return null;
        }

        $method = $request['http']['method'];
        $url    = $request['http']['url'];
        Assert::assertIsString($method);
        Assert::assertIsString($url);
        $path = self::normalize(str_replace('{{baseUrl}}', '', $url));
        foreach ($paths->getPaths() as $pattern => $pathItem) {
            self::assertRequest($request, $method, $path, $pattern, $pathItem);
        }

        return $method . ' ' . $path;
    }

    /** @param array<string, mixed> $request */
    private static function assertRequest(array $request, string $method, string $path, string $pattern, PathItem $pathItem): void
    {
        if ($path !== self::normalize($pattern)) {
            return;
        }

        $operation = $pathItem->getOperations()[strtolower($method)] ?? null;
        Assert::assertInstanceOf(Operation::class, $operation);
        $scripts = $request['runtime']['scripts'];
        Assert::assertIsArray($scripts);
        $tests = [];
        foreach ($scripts as $script) {
            if ($script['type'] !== 'tests') {
                continue;
            }

            $tests[] = $script['code'];
        }

        $code = implode("\n", $tests);
        Assert::assertStringContainsString('expect(res.status)', $code);
        $responses = $operation->responses;
        Assert::assertInstanceOf(Responses::class, $responses);
        $success = $responses->getResponse('200') ?? $responses->getResponse('201');
        if ($success === null) {
            Assert::assertStringContainsString('204', $code);

            return;
        }

        Assert::assertInstanceOf(Response::class, $success);
        $status = $responses->getResponse('200') === null ? 201 : 200;
        Assert::assertMatchesRegularExpression('/\b' . $status . '\b/', $code);
        $schema = $success->content['application/json']->schema;
        Assert::assertInstanceOf(Schema::class, $schema);
        foreach ($schema->properties as $name => $property) {
            $field = preg_quote($name, '/');
            Assert::assertMatchesRegularExpression('/body\.' . $field . '\b|\.property\("' . $field . '"\)|\{ ' . $field . ':/', $code);
        }

        if (str_contains($code, 'expect(res.getBody()).to.deep.equal')) {
            return;
        }

        Assert::assertStringContainsString('expect(body).not.to.have.property("data")', $code);
        Assert::assertStringContainsString('expect(body).not.to.have.property("statusCode")', $code);
    }
}
