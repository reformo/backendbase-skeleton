<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\ExampleApi\Contracts;

use Backendbase\Infrastructure\Adapters\Http\Actions\Action;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\Handlers\ChangeExampleDetails;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\Handlers\NewExample;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\Handlers\RemoveExample;
use Backendbase\Shared\CQRS\CommandBus;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tests\Support\Http\ApiResponseAssertions;
use Tests\Support\Http\JsonRequestBody;

final class EmptyResponseContractTest extends TestCase
{
    /** @param class-string<Action> $class */
    #[DataProvider('operations')]
    public function testExampleWritesPreserveTheirDocumentedEmptyResponse(string $class, string $method, string $path): void
    {
        $commands = $this->createMock(CommandBus::class);
        $commands->expects(self::once())->method('handle');
        $action   = new $class($commands, $this->createStub(LoggerInterface::class));
        $request  = new ServerRequestFactory()->createServerRequest($method, '/examples');
        $request  = $request->withAttribute('type-slug', 'system');
        $request  = $request->withAttribute('example-group', 'settings');
        $request  = $request->withAttribute('example-key', 'feature');
        $payload  = $method === 'POST' ? ['lookupKey' => 'feature', 'lookupValue' => 'enabled'] : ['lookupValue' => 'enabled'];
        $request  = $request->withBody(JsonRequestBody::create($payload));
        $response = $action($request, new Response(), []);
        self::assertSame(204, $response->getStatusCode());
        ApiResponseAssertions::assertMatches('example-api', $method, $path, $response);
    }

    /** @return iterable<string, array{class-string<Action>, string, string}> */
    public static function operations(): iterable
    {
        yield 'create' => [NewExample::class, 'POST', '/example-types/{type-slug}/groups/{example-group}/examples'];
        yield 'change' => [ChangeExampleDetails::class, 'PATCH', '/example-types/{type-slug}/groups/{example-group}/examples/{example-key}'];
        yield 'remove' => [RemoveExample::class, 'DELETE', '/example-types/{type-slug}/groups/{example-group}/examples/{example-key}'];
    }
}
