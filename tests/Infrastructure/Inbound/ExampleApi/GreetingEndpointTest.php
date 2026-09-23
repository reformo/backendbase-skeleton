<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\ExampleApi;

use Backendbase\Domain\ExampleCatalog\Contracts\Command\QueueGreeting;
use Backendbase\Domain\IdentityAndAccess\Authorization\Acl;
use Backendbase\Domain\IdentityAndAccess\Exception\AuthorizationExpired;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Greeting\Handlers\QueueGreetingRequest;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\Exception\InvalidUserInput;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

use function str_repeat;

final class GreetingEndpointTest extends TestCase
{
    #[Test]
    public function itQueuesAValidatedFullName(): void
    {
        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->expects(self::once())->method('handle')->with(
            self::callback(static function (QueueGreeting $command): bool {
                self::assertSame('Ada Lovelace', $command->fullName());

                return true;
            }),
        );
        $action  = new QueueGreetingRequest($commandBus, new NullLogger());
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/examples/hello')
            ->withAttribute(AccessControl::class, new Acl(['full-privileges']))
            ->withParsedBody(['fullname' => '  Ada Lovelace  ']);

        $response = $action($request, new Response(), []);

        self::assertSame(202, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    #[Test]
    public function itRejectsInvalidFullNamesBeforeDispatch(): void
    {
        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->expects(self::never())->method('handle');
        $action        = new QueueGreetingRequest($commandBus, new NullLogger());
        $invalidBodies = [
            null,
            [],
            ['fullname' => []],
            ['fullname' => '  '],
            ['fullname' => str_repeat('a', 101)],
            ['fullname' => "Ada\nLovelace"],
        ];

        foreach ($invalidBodies as $body) {
            $request = (new ServerRequestFactory())->createServerRequest('POST', '/examples/hello')
                ->withAttribute(AccessControl::class, new Acl(['full-privileges']))
                ->withParsedBody($body);

            try {
                $action($request, new Response(), []);
                self::fail('Invalid fullname input must fail.');
            } catch (InvalidUserInput) {
                self::addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function itRejectsMissingAuthorizationBeforeDispatch(): void
    {
        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->expects(self::never())->method('handle');
        $action  = new QueueGreetingRequest($commandBus, new NullLogger());
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/examples/hello')
            ->withParsedBody(['fullname' => 'Ada Lovelace']);

        $this->expectException(AuthorizationExpired::class);

        $action($request, new Response(), []);
    }
}
