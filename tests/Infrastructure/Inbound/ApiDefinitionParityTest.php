<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound;

use cebe\openapi\spec\Operation;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Paths;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Responses;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Http\ApiResponseAssertions;
use Tests\Support\Http\BrunoCollectionAssertions;

use function json_decode;
use function json_encode;
use function sort;
use function strtoupper;

use const JSON_THROW_ON_ERROR;

final class ApiDefinitionParityTest extends TestCase
{
    #[DataProvider('apis')]
    public function testSourceMergedAndBrunoCoverTheSameResponseContracts(string $slug, int $operationCount): void
    {
        $sourceName  = $slug === 'example-api' ? 'example' : $slug;
        $source      = ApiResponseAssertions::document('resources/api-docs/' . $slug . '/' . $sourceName . '-openapi.yml');
        $merged      = ApiResponseAssertions::document('public/' . $slug . '/docs/' . $slug . '-merged.yml');
        $sourcePaths = $source->paths;
        $mergedPaths = $merged->paths;
        self::assertInstanceOf(Paths::class, $sourcePaths);
        self::assertInstanceOf(Paths::class, $mergedPaths);
        self::assertEquals($sourcePaths->getSerializableData(), $mergedPaths->getSerializableData());
        $operations = $this->documentedOperations($sourcePaths);
        self::assertCount($operationCount, $operations);
        self::assertSame($operations, BrunoCollectionAssertions::operations($slug, $sourcePaths));
    }

    /** @return iterable<string, array{string, int}> */
    public static function apis(): iterable
    {
        yield 'ExampleApi' => ['example-api', 9];
        yield 'UserApi' => ['user-api', 10];
        yield 'SystemAdminApi' => ['system-admin-api', 13];
    }

    /**
     * @param Paths<PathItem> $paths
     *
     * @return list<string>
     */
    private function documentedOperations(Paths $paths): array
    {
        $operations = [];
        foreach ($paths->getPaths() as $path => $pathItem) {
            $operations = [...$operations, ...$this->pathOperations($path, $pathItem->getOperations())];
        }

        sort($operations);

        return $operations;
    }

    /**
     * @param array<string, Operation> $definitions
     *
     * @return list<string>
     */
    private function pathOperations(string $path, array $definitions): array
    {
        $operations = [];
        foreach ($definitions as $method => $definition) {
            $operations[] = strtoupper($method) . ' ' . BrunoCollectionAssertions::normalize($path);
            $responses    = $definition->responses;
            self::assertInstanceOf(Responses::class, $responses);
            $this->assertResponseKeys($responses->getResponses());
        }

        return $operations;
    }

    /** @param array<int|string, Response|Reference|null> $responses */
    private function assertResponseKeys(array $responses): void
    {
        foreach ($responses as $status => $response) {
            self::assertNotNull($response);
            $description = json_decode(json_encode($response->getSerializableData(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($description);
            $this->assertSuccessKeys($status, $description);
        }
    }

    /** @param array<string, mixed> $response */
    private function assertSuccessKeys(int|string $status, array $response): void
    {
        if ((int) $status < 200 || (int) $status >= 300 || ! isset($response['content'])) {
            return;
        }

        $schema = $response['content']['application/json']['schema'];
        self::assertIsArray($schema);
        self::assertSame('object', $schema['type']);
        $properties = $schema['properties'];
        self::assertIsArray($properties);
        self::assertArrayNotHasKey('data', $properties);
        self::assertArrayNotHasKey('statusCode', $properties);
        foreach ($properties as $name => $definition) {
            self::assertIsString($name);
            self::assertMatchesRegularExpression('/^[a-z][a-zA-Z0-9]*$/D', $name);
        }
    }
}
