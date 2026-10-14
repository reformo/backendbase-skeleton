<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\ExampleApi\Contracts;

use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryDetails;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryListItem;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryPage;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Infrastructure\Configuration\ApplicationRuntimeSettings;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\Handlers\ExampleDetails;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\Handlers\Examples;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\Services\Settings;
use DateTimeImmutable;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tests\Support\Http\ApiResponseAssertions;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class EmptyObjectResponseTest extends TestCase
{
    #[DataProvider('projections')]
    public function testEmptyDetailsRemainAJsonObject(bool $collection): void
    {
        $time    = new DateTimeImmutable('2026-10-14T10:00:00+00:00');
        $result  = $collection
            ? new EntryPage([new EntryListItem('example-id', 'feature', 'enabled', [], true, $time)], 1)
            : new EntryDetails('example-id', EntryType::SYSTEM, null, 'settings', 'feature', 'enabled', [], true, $time, $time);
        $queries = $this->createStub(QueryBus::class);
        $queries->method('handle')->willReturn($result);
        $logger   = $this->createStub(LoggerInterface::class);
        $action   = $collection
            ? new Examples($queries, new ApplicationRuntimeSettings(new Settings(['cdnBaseUrl' => 'https://cdn.example.com/'])), $logger)
            : new ExampleDetails($queries, $logger);
        $request  = new ServerRequestFactory()->createServerRequest('GET', '/examples');
        $request  = $request->withAttribute('type-slug', 'system');
        $request  = $request->withAttribute('example-group', 'settings');
        $request  = $request->withAttribute('example-key', 'feature');
        $response = $action($request, new Response(), []);
        $path     = '/example-types/{type-slug}/groups/{example-group}/examples' . ($collection ? '' : '/{example-key}');
        ApiResponseAssertions::assertMatches('example-api', 'GET', $path, $response);
        $body    = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $details = $collection ? $body['examples'][0]['details'] : $body['example']['details'];
        self::assertSame([], $details);
        self::assertStringContainsString('"details":{}', (string) $response->getBody());
    }

    /** @return iterable<string, array{bool}> */
    public static function projections(): iterable
    {
        yield 'collection' => [true];
        yield 'resource' => [false];
    }
}
