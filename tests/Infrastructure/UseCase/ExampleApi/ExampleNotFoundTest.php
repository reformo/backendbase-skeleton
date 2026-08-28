<?php

declare(strict_types=1);

namespace Tests\Infrastructure\UseCase\ExampleApi;

use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers\ChangeExampleDetails;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers\ExampleDetails;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers\RemoveExample;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\Exception\ResourceNotFound;
use Backendbase\Shared\Http\Actions\Action;
use Backendbase\Shared\Http\Handlers\HttpErrorHandler;
use Laminas\Diactoros\ServerRequestFactory;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Factory\AppFactory;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class ExampleNotFoundTest extends TestCase
{
    #[Test]
    public function itReturnsNotFoundForMissingExampleResources(): void
    {
        $queryBus = $this->createStub(QueryBus::class);
        $queryBus->method('handle')->willReturn(null);
        $commandBus = $this->createStub(CommandBus::class);
        $commandBus->method('handle')->willThrowException(
            ResourceNotFound::create('The example was not found.'),
        );
        $logger  = new Logger('example-not-found-test');
        $actions = [
            ['GET', new ExampleDetails($queryBus, $logger, null)],
            ['PATCH', new ChangeExampleDetails($commandBus, $logger, null)],
            ['DELETE', new RemoveExample($commandBus, $logger, null)],
        ];

        foreach ($actions as [$method, $action]) {
            $response = $this->request($method, $action, $logger);
            $payload  = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            self::assertSame(404, $response->getStatusCode());
            self::assertSame('general/resource-not-found', $payload['code']);
        }
    }

    private function request(string $method, Action $action, Logger $logger): ResponseInterface
    {
        $app = AppFactory::create();
        $app->map(
            [$method],
            '/examples/{typeSlug}/{exampleGroup}/{exampleKey}',
            $action,
        );
        $app->addRoutingMiddleware();
        $errorMiddleware = $app->addErrorMiddleware(false, true, true);
        $errorMiddleware->setDefaultErrorHandler(new HttpErrorHandler(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            $logger,
        ));
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, '/examples/system/settings/key')
            ->withParsedBody([]);

        return $app->handle($request);
    }
}
