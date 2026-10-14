<?php

declare(strict_types=1);

namespace Tests\Support\Http;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Paths;
use cebe\openapi\spec\Response as ResponseDefinition;
use cebe\openapi\spec\Responses;
use cebe\openapi\spec\Schema;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

use function explode;
use function json_decode;
use function json_encode;
use function realpath;
use function strtolower;

use const JSON_THROW_ON_ERROR;

final class ApiResponseAssertions
{
    public static function document(string $file): OpenApi
    {
        $path = realpath($file);
        Assert::assertIsString($path);
        $document = Reader::readFromYamlFile($path, resolveReferences: 'all');
        Assert::assertInstanceOf(OpenApi::class, $document);

        return $document;
    }

    public static function assertMatches(string $slug, string $method, string $path, ResponseInterface $response): void
    {
        $sourceName = $slug === 'example-api' ? 'example' : $slug;
        foreach (['resources/api-docs/' . $slug . '/' . $sourceName . '-openapi.yml', 'public/' . $slug . '/docs/' . $slug . '-merged.yml'] as $file) {
            $document = self::document($file);
            self::assertDocumentResponse($document, $method, $path, $response);
        }
    }

    private static function assertDocumentResponse(OpenApi $document, string $method, string $path, ResponseInterface $response): void
    {
        $paths = $document->paths;
        Assert::assertInstanceOf(Paths::class, $paths);
        $pathItem = $paths->getPath($path);
        Assert::assertInstanceOf(PathItem::class, $pathItem);
        $operations = $pathItem->getOperations();
        $operation  = $operations[strtolower($method)] ?? null;
        Assert::assertInstanceOf(Operation::class, $operation);
        $status    = $response->getStatusCode();
        $responses = $operation->responses;
        Assert::assertInstanceOf(Responses::class, $responses);
        $definition = $responses->getResponse((string) $status);
        Assert::assertInstanceOf(ResponseDefinition::class, $definition);
        $content = $definition->content ?? [];
        if ($content === []) {
            Assert::assertSame('', (string) $response->getBody());

            return;
        }

        $mediaType = explode(';', $response->getHeaderLine('Content-Type'))[0];
        Assert::assertArrayHasKey($mediaType, $content);
        $media  = $content[$mediaType];
        $schema = $media->schema;
        Assert::assertInstanceOf(Schema::class, $schema);
        $description = json_decode(json_encode($schema->getSerializableData(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        Assert::assertIsArray($description);
        $body = json_decode((string) $response->getBody(), flags: JSON_THROW_ON_ERROR);
        JsonShapeAssertions::assertMatches($body, $description);
    }
}
