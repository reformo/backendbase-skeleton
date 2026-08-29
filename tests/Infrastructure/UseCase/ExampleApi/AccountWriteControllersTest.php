<?php

declare(strict_types=1);

namespace Tests\Infrastructure\UseCase\ExampleApi;

use Backendbase\Domain\IdentityAndAccess\Authorization\Acl;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\RegisterAccount as RegisterAccountCommand;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\RetireAccount as RetireAccountCommand;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\ReviseAccount as ReviseAccountCommand;
use Backendbase\Domain\IdentityAndAccess\Exception\AuthorizationExpired;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Account\AccountRequestInput;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Account\Handlers\RegisterAccount;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Account\Handlers\RetireAccount;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Account\Handlers\ReviseAccount;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\CQRS\Command;
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
use SensitiveParameterValue;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class AccountWriteControllersTest extends TestCase
{
    #[Test]
    public function itRegistersAnAccountFromValidatedInput(): void
    {
        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->expects(self::once())
            ->method('handle')
            ->with(self::callback(static function (Command $command): bool {
                if (! $command instanceof RegisterAccountCommand) {
                    return false;
                }

                self::assertSame('account@example.com', $command->email()->toString());
                self::assertSame(['account.register'], $command->privileges()->slugs());
                self::assertTrue($command->passwordHash()->verifyHash(new SensitiveParameterValue('secret')));

                return true;
            }));
        $action = new RegisterAccount($commandBus, $this->createStub(LoggerInterface::class));

        $response = $this->invoke($action, $this->request('POST', '/accounts', [
            'email' => 'account@example.com',
            'password' => 'secret',
            'privilegeSlugs' => ['account.register'],
        ]));
        $payload  = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(201, $response->getStatusCode());
        self::assertIsString($payload['accountUuid']);
    }

    #[Test]
    public function itRevisesAnAccountFromValidatedInput(): void
    {
        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->expects(self::once())
            ->method('handle')
            ->with(self::callback(static function (Command $command): bool {
                if (! $command instanceof ReviseAccountCommand) {
                    return false;
                }

                return $command->accountId()->toString() === '4bb3fe29-8b80-463e-9d42-b3a9298a7586'
                    && $command->email() === null
                    && $command->privileges()?->slugs() === ['account.list'];
            }));
        $action = new ReviseAccount($commandBus, $this->createStub(LoggerInterface::class));

        $response = $this->invoke($action, $this->request(
            'PATCH',
            '/accounts/4bb3fe29-8b80-463e-9d42-b3a9298a7586',
            ['privilegeSlugs' => ['account.list']],
            ['account-uuid' => '4bb3fe29-8b80-463e-9d42-b3a9298a7586'],
        ));

        self::assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function itRetiresAnAccountFromAValidatedPathParameter(): void
    {
        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->expects(self::once())
            ->method('handle')
            ->with(self::callback(static function (Command $command): bool {
                return $command instanceof RetireAccountCommand
                    && $command->accountId()->toString() === '4bb3fe29-8b80-463e-9d42-b3a9298a7586';
            }));
        $action = new RetireAccount($commandBus, $this->createStub(LoggerInterface::class));

        $response = $this->invoke($action, $this->request(
            'DELETE',
            '/accounts/4bb3fe29-8b80-463e-9d42-b3a9298a7586',
            null,
            ['account-uuid' => '4bb3fe29-8b80-463e-9d42-b3a9298a7586'],
        ));

        self::assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function itRejectsInvalidAccountPayloadsBeforeDispatchingCommands(): void
    {
        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->expects(self::never())->method('handle');
        $action = new RegisterAccount($commandBus, $this->createStub(LoggerInterface::class));

        foreach ([null, [], ['email' => 'account@example.com', 'password' => 'secret']] as $payload) {
            try {
                $this->invoke($action, $this->request('POST', '/accounts', $payload));
                self::fail('Invalid account input must fail before command dispatch.');
            } catch (InvalidUserInput) {
                self::addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function itRejectsInvalidAccountBoundaryValues(): void
    {
        try {
            AccountRequestInput::accessControl(null);
            self::fail('A missing access control value must fail.');
        } catch (AuthorizationExpired) {
            self::addToAssertionCount(1);
        }

        foreach (
            [
                static fn () => AccountRequestInput::accountId(null),
                static fn () => AccountRequestInput::revisionPayload([]),
                static fn () => AccountRequestInput::email(null),
                static fn () => AccountRequestInput::passwordHash(''),
                static fn () => AccountRequestInput::privilegeSlugs('account.list'),
                static fn () => AccountRequestInput::privilegeSlugs([null]),
                static fn () => AccountRequestInput::registrationPayload(['invalid']),
                static fn () => AccountRequestInput::registrationPayload(['unsupported' => true]),
            ] as $invalidInput
        ) {
            try {
                $invalidInput();
                self::fail('An invalid account boundary value must fail.');
            } catch (InvalidUserInput) {
                self::addToAssertionCount(1);
            }
        }

        self::assertNull(AccountRequestInput::optionalEmail([]));
        self::assertNull(AccountRequestInput::optionalPasswordHash([]));
        self::assertNull(AccountRequestInput::optionalPrivilegeSlugs([]));
        self::assertSame(
            'revised@example.com',
            AccountRequestInput::optionalEmail(['email' => 'revised@example.com'])?->toString(),
        );
        self::assertTrue(
            AccountRequestInput::optionalPasswordHash(['password' => 'revised-secret'])
                ?->verifyHash(new SensitiveParameterValue('revised-secret')) ?? false,
        );
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<string, mixed>      $attributes
     */
    private function request(string $method, string $path, array|null $payload, array $attributes = []): ServerRequestInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path)
            ->withAttribute(AccessControl::class, new Acl(['full-privileges']))
            ->withParsedBody($payload);
        foreach ($attributes as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        return $request;
    }

    private function invoke(Action $action, ServerRequestInterface $request): ResponseInterface
    {
        return $action($request, new Response(), []);
    }
}
