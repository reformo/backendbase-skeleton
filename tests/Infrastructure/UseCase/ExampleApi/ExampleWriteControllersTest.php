<?php

declare(strict_types=1);

namespace Tests\Infrastructure\UseCase\ExampleApi;

use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\AddNewExample;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\ChangeExample;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\RemoveExample as RemoveExampleCommand;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers\ChangeExampleDetails;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers\NewExample;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers\RemoveExample;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\Http\Actions\Action;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final class ExampleWriteControllersTest extends TestCase
{
    #[Test]
    public function itAddsANewExample(): void
    {
        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->expects(self::once())
            ->method('handle')
            ->with(self::callback(static function (AddNewExample $command): bool {
                self::assertSame('settings', $command->group());
                self::assertSame('page-size', $command->key());
                self::assertSame('25 & items', $command->value());
                self::assertSame(['unit' => 'items'], $command->details());

                return true;
            }));
        $action  = new NewExample($commandBus, $this->createStub(LoggerInterface::class), null);
        $request = $this->request('POST', '/examples')
            ->withAttribute('typeSlug', 'system')
            ->withAttribute('exampleGroup', 'settings')
            ->withParsedBody([
                'lookupKey' => 'page-size',
                'lookupValue' => '25 & items',
                'details' => ['unit' => 'items'],
            ]);

        $response = $this->invoke($action, $request);

        self::assertSame(204, $response->getStatusCode());
        self::assertNotSame('', $response->getHeaderLine('Backendbase-Insert-Id'));
    }

    #[Test]
    public function itRejectsInvalidNewExampleInput(): void
    {
        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->expects(self::never())->method('handle');
        $action   = new NewExample($commandBus, $this->createStub(LoggerInterface::class), null);
        $payloads = [
            [],
            ['lookupValue' => []],
            ['lookupValue' => 'value', 'lookupKey' => []],
            ['lookupValue' => 'value', 'details' => 'details'],
            ['lookupValue' => 'value', 'isActive' => 'true'],
        ];

        foreach ($payloads as $payload) {
            $request = $this->request('POST', '/examples')
                ->withAttribute('typeSlug', 'system')
                ->withAttribute('exampleGroup', 'settings')
                ->withParsedBody($payload);

            self::assertSame(400, $this->invoke($action, $request)->getStatusCode());
        }
    }

    #[Test]
    public function itChangesAnExistingExample(): void
    {
        $queryBus = $this->createStub(QueryBus::class);
        $queryBus->method('handle')->willReturn('example-id');
        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->expects(self::once())
            ->method('handle')
            ->with(self::callback(static function (ChangeExample $command): bool {
                return $command->exampleId() === 'example-id'
                    && $command->value() === '50'
                    && $command->isActive() === false
                    && $command->details() === ['unit' => 'rows'];
            }));
        $action  = new ChangeExampleDetails(
            $queryBus,
            $commandBus,
            $this->createStub(LoggerInterface::class),
            null,
        );
        $request = $this->request('PATCH', '/examples/page-size')
            ->withAttribute('typeSlug', 'system')
            ->withAttribute('exampleGroup', 'settings')
            ->withAttribute('exampleKey', 'page-size')
            ->withParsedBody([
                'lookupValue' => '50',
                'isActive' => false,
                'details' => ['unit' => 'rows'],
            ]);

        self::assertSame(204, $this->invoke($action, $request)->getStatusCode());
    }

    #[Test]
    public function itRemovesAnExistingExample(): void
    {
        $queryBus = $this->createStub(QueryBus::class);
        $queryBus->method('handle')->willReturn('example-id');
        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->expects(self::once())
            ->method('handle')
            ->with(self::callback(static function (Command $command): bool {
                return $command instanceof RemoveExampleCommand
                    && $command->exampleId() === 'example-id';
            }));
        $action  = new RemoveExample(
            $queryBus,
            $commandBus,
            $this->createStub(LoggerInterface::class),
            null,
        );
        $request = $this->request('DELETE', '/examples/page-size')
            ->withAttribute('typeSlug', 'system')
            ->withAttribute('exampleGroup', 'settings')
            ->withAttribute('exampleKey', 'page-size');

        self::assertSame(204, $this->invoke($action, $request)->getStatusCode());
    }

    private function request(string $method, string $uri): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest($method, $uri);
    }

    private function invoke(Action $action, ServerRequestInterface $request): ResponseInterface
    {
        return $action($request, new Response(), []);
    }
}
