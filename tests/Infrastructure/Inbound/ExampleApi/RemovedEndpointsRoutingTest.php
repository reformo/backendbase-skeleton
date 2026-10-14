<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\ExampleApi;

use Backendbase\Shared\CQRS\CommandBus;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class RemovedEndpointsRoutingTest extends TestCase
{
    #[Test]
    #[DataProvider('removedEndpoints')]
    public function itReturnsNotFoundWithoutDispatchingACommand(string $method, string $path): void
    {
        $bus = $this->createMock(CommandBus::class);
        $bus->expects(self::never())->method('handle');
        $app     = ExampleApiFixture::build($bus);
        $request = (new ServerRequestFactory())->createServerRequest($method, $path)
            ->withHeader('Backendbase-Api-Key', 'example-api-key');

        $response = $app->handle($request);
        $payload  = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('http/not-found', $payload['code']);
        self::assertSame('Not Found', $payload['title']);
        self::assertSame(404, $payload['status']);
    }

    /** @return iterable<string, array{string, string}> */
    public static function removedEndpoints(): iterable
    {
        yield 'list accounts' => ['GET', '/accounts'];
        yield 'register account' => ['POST', '/accounts'];
        yield 'revise account' => ['PATCH', '/accounts/7d9f6812-34f8-4bce-9396-82e97c9dd0ce'];
        yield 'retire account' => ['DELETE', '/accounts/7d9f6812-34f8-4bce-9396-82e97c9dd0ce'];
        yield 'authenticate' => ['POST', '/auth'];
        yield 'queue greeting' => ['POST', '/examples/hello'];
    }
}
