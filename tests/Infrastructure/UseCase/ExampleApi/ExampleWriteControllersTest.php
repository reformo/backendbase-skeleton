<?php

declare(strict_types=1);

namespace Tests\Infrastructure\UseCase\ExampleApi;

use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\AddNewExample;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\ChangeExample;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Command\RemoveExample as RemoveExampleCommand;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
use Backendbase\Domain\IdentityAndAccess\Authorization\Acl;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers\ChangeExampleDetails;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers\NewExample;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers\RemoveExample;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\Exception\InvalidUserInput;
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
        $action  = new NewExample($commandBus, $this->createStub(LoggerInterface::class));
        $request = $this->request('POST', '/examples')
            ->withAttribute('typeSlug', 'system')
            ->withAttribute('exampleGroup', 'settings')
            ->withAttribute(AccessControl::class, new Acl(['full-privileges']))
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
        $action   = new NewExample($commandBus, $this->createStub(LoggerInterface::class));
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
                ->withAttribute(AccessControl::class, new Acl(['full-privileges']))
                ->withParsedBody($payload);

            $this->assertInvalidNewExampleInput($action, $request);
        }
    }

    #[Test]
    public function itChangesAnExistingExample(): void
    {
        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->expects(self::once())
            ->method('handle')
            ->with(self::callback(static function (ChangeExample $command): bool {
                $identity = $command->identity();

                return $identity->type() === ExampleType::SYSTEM
                    && $identity->typeTargetId() === null
                    && $identity->group() === 'settings'
                    && $identity->key() === 'page-size'
                    && $command->value() === '50'
                    && $command->isActive() === false
                    && $command->details() === ['unit' => 'rows'];
            }));
        $action  = new ChangeExampleDetails(
            $commandBus,
            $this->createStub(LoggerInterface::class),
        );
        $request = $this->request('PATCH', '/examples/page-size')
            ->withAttribute('typeSlug', 'system')
            ->withAttribute('exampleGroup', 'settings')
            ->withAttribute('exampleKey', 'page-size')
            ->withAttribute(AccessControl::class, new Acl(['full-privileges']))
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
        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->expects(self::once())
            ->method('handle')
            ->with(self::callback(static function (RemoveExampleCommand $command): bool {
                $identity = $command->identity();

                return $identity->type() === ExampleType::SYSTEM
                    && $identity->typeTargetId() === null
                    && $identity->group() === 'settings'
                    && $identity->key() === 'page-size';
            }));
        $action  = new RemoveExample(
            $commandBus,
            $this->createStub(LoggerInterface::class),
        );
        $request = $this->request('DELETE', '/examples/page-size')
            ->withAttribute('typeSlug', 'system')
            ->withAttribute('exampleGroup', 'settings')
            ->withAttribute('exampleKey', 'page-size')
            ->withAttribute(AccessControl::class, new Acl(['full-privileges']));

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

    private function assertInvalidNewExampleInput(Action $action, ServerRequestInterface $request): void
    {
        try {
            $this->invoke($action, $request);
            self::fail('Invalid new-example input must fail.');
        } catch (InvalidUserInput) {
            self::addToAssertionCount(1);
        }
    }
}
