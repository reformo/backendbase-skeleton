<?php

declare(strict_types=1);

namespace Tests\Shared\Http\Middleware;

use Backendbase\Shared\Configuration\ApiKeySettings;
use Backendbase\Shared\Http\Middleware\ValidateApiKey;
use Backendbase\Shared\Services\Settings;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ValidateApiKeyTest extends TestCase
{
    #[Test]
    public function itAcceptsOptionsAndPublicPaths(): void
    {
        $middleware = new ValidateApiKey(new ApiKeySettings(new Settings([])));
        $handler    = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn(new EmptyResponse(204));

        $options = $middleware->process($this->request('OPTIONS', '/private'), $handler);
        self::assertSame(200, $options->getStatusCode());
        $public = $middleware->process($this->request('GET', '/_status/ready'), $handler);
        self::assertSame(204, $public->getStatusCode());
    }

    #[Test]
    public function itValidatesIdentifierBasedApiKeys(): void
    {
        $middleware = new ValidateApiKey(new ApiKeySettings(new Settings([])));
        $handler    = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn(new EmptyResponse(204));
        $request = $this->request('GET', '/private')
            ->withAttribute('apiKeyHeaderName', 'X-Api-Key')
            ->withAttribute('useIdentifierAsApiKey', true);

        self::assertSame(400, $middleware->process($request, $handler)->getStatusCode());
        self::assertSame(
            204,
            $middleware->process($request->withHeader('X-Api-Key', 'identifier'), $handler)->getStatusCode(),
        );
    }

    #[Test]
    public function itValidatesConfiguredApiKeys(): void
    {
        $settings   = new Settings([
            'exampleApi' => ['api-key' => 'expected-key'],
        ]);
        $middleware = new ValidateApiKey(new ApiKeySettings($settings));
        $handler    = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn(new EmptyResponse(204));
        $request = $this->request('GET', '/private')
            ->withAttribute('apiKeyHeaderName', 'X-Api-Key')
            ->withAttribute('useIdentifierAsApiKey', false)
            ->withAttribute('apiName', 'exampleApi');

        self::assertSame(400, $middleware->process($request, $handler)->getStatusCode());
        self::assertSame(
            204,
            $middleware->process($request->withHeader('X-Api-Key', 'expected-key'), $handler)->getStatusCode(),
        );
        self::assertSame(400, $middleware->process($request->withUri($request->getUri()->withPath('/')), $handler)->getStatusCode());
    }

    private function request(string $method, string $path): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest($method, $path);
    }
}
