<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\ExampleApi;

use Backendbase\Infrastructure\Configuration\ApplicationRuntimeSettings;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Root\NotFound;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Root\Root;
use Backendbase\Shared\Services\Settings;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class RootControllersTest extends TestCase
{
    #[Test]
    public function itReturnsApiMetadata(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with('Root endpoint called');
        $settings = new ApplicationRuntimeSettings(new Settings(['cdnBaseUrl' => 'https://cdn.example.com/']));
        $action   = new Root($settings, $logger);
        $request  = (new ServerRequestFactory())->createServerRequest('GET', '/');

        $response = $action($request, new Response(), []);
        $payload  = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('https://cdn.example.com/', $payload['backendbase-api']['cdnBaseUrl']);
        self::assertArrayNotHasKey('health', $payload);
    }

    #[Test]
    public function itReturnsNotFoundAndAcceptsOptions(): void
    {
        $action  = new NotFound($this->createStub(LoggerInterface::class));
        $factory = new ServerRequestFactory();

        $notFound = $action($factory->createServerRequest('GET', '/missing'), new Response(), []);
        self::assertSame(404, $notFound->getStatusCode());
        $payload = json_decode((string) $notFound->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('http/not-found', $payload['code']);
        self::assertSame('Not Found', $payload['title']);
        self::assertSame(404, $payload['status']);

        $options = $action($factory->createServerRequest('OPTIONS', '/missing'), new Response(), []);
        self::assertSame(204, $options->getStatusCode());
    }
}
