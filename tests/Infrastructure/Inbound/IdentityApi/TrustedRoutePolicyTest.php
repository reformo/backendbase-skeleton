<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Infrastructure\Adapters\Http\Identity\IdentityHttpInput;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers as ExampleControllers;
use Backendbase\Infrastructure\Inbound\SystemAdminApi\Controllers as AdminControllers;
use Backendbase\Infrastructure\Inbound\SystemAdminApi\Controllers\Administration\AdministrationHttpInput;
use Backendbase\Infrastructure\Inbound\UserApi\Controllers as UserControllers;
use Backendbase\Shared\Exception\InvalidUserInput;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class TrustedRoutePolicyTest extends TestCase
{
    /** @param class-string<Throwable> $exception */
    #[DataProvider('missingPolicies')]
    public function testControllerMisconfigurationCannotBypassTrustedPolicy(string $controllerName, string $exception): void
    {
        $fixture    = IdentityFixture::create();
        $container  = $fixture->container();
        $controller = $container->get($controllerName);
        $this->expectException($exception);
        $controller(new ServerRequest(), new Response(), []);
    }

    /** @return iterable<string, array{string, class-string<Throwable>}> */
    public static function missingPolicies(): iterable
    {
        yield 'user RefreshSession policy' => [UserControllers\Authentication\Handlers\RefreshSession::class, RuntimeException::class];
        yield 'user PasswordLogin policy' => [UserControllers\Authentication\Handlers\PasswordLogin::class, RuntimeException::class];
        yield 'user RevokeAccessToken policy' => [UserControllers\Identity\Handlers\RevokeAccessToken::class, RuntimeException::class];
        yield 'user EndSession policy' => [UserControllers\Identity\Handlers\EndSession::class, RuntimeException::class];
        yield 'user CloseAccount policy' => [UserControllers\Identity\Handlers\CloseAccount::class, RuntimeException::class];
        yield 'user RegisterUser policy' => [UserControllers\Authentication\Handlers\RegisterUser::class, RuntimeException::class];
        yield 'user identity principal' => [UserControllers\Identity\Handlers\CurrentIdentity::class, AuthenticationDenied::class];
        yield 'user Authentication RequestContactVerification policy' => [UserControllers\Authentication\Handlers\RequestContactVerification::class, AuthenticationDenied::class];
        yield 'user Authentication ConfirmContactVerification policy' => [UserControllers\Authentication\Handlers\ConfirmContactVerification::class, AuthenticationDenied::class];
        yield 'user Authentication ResendContactVerification policy' => [UserControllers\Authentication\Handlers\ResendContactVerification::class, AuthenticationDenied::class];
        yield 'example RefreshSession policy' => [ExampleControllers\Authentication\Handlers\RefreshSession::class, RuntimeException::class];
        yield 'example PasswordLogin policy' => [ExampleControllers\Authentication\Handlers\PasswordLogin::class, RuntimeException::class];
        yield 'example RevokeAccessToken policy' => [ExampleControllers\Identity\Handlers\RevokeAccessToken::class, RuntimeException::class];
        yield 'example EndSession policy' => [ExampleControllers\Identity\Handlers\EndSession::class, RuntimeException::class];
        yield 'example CloseAccount policy' => [ExampleControllers\Identity\Handlers\CloseAccount::class, RuntimeException::class];
        yield 'example RegisterUser policy' => [ExampleControllers\Authentication\Handlers\RegisterUser::class, RuntimeException::class];
        yield 'example identity principal' => [ExampleControllers\Identity\Handlers\CurrentIdentity::class, AuthenticationDenied::class];
        yield 'example Authentication RequestContactVerification policy' => [ExampleControllers\Authentication\Handlers\RequestContactVerification::class, AuthenticationDenied::class];
        yield 'example Authentication ConfirmContactVerification policy' => [ExampleControllers\Authentication\Handlers\ConfirmContactVerification::class, AuthenticationDenied::class];
        yield 'example Authentication ResendContactVerification policy' => [ExampleControllers\Authentication\Handlers\ResendContactVerification::class, AuthenticationDenied::class];
        yield 'admin RefreshSession policy' => [AdminControllers\Authentication\Handlers\RefreshSession::class, RuntimeException::class];
        yield 'admin PasswordLogin policy' => [AdminControllers\Authentication\Handlers\PasswordLogin::class, RuntimeException::class];
        yield 'admin RevokeAccessToken policy' => [AdminControllers\Identity\Handlers\RevokeAccessToken::class, RuntimeException::class];
        yield 'admin EndSession policy' => [AdminControllers\Identity\Handlers\EndSession::class, RuntimeException::class];
        yield 'admin CloseAccount policy' => [AdminControllers\Identity\Handlers\CloseAccount::class, RuntimeException::class];
        yield 'admin identity principal' => [AdminControllers\Identity\Handlers\CurrentIdentity::class, AuthenticationDenied::class];
        yield 'admin Authentication RequestContactVerification policy' => [AdminControllers\Authentication\Handlers\RequestContactVerification::class, AuthenticationDenied::class];
        yield 'admin Authentication ConfirmContactVerification policy' => [AdminControllers\Authentication\Handlers\ConfirmContactVerification::class, AuthenticationDenied::class];
        yield 'admin Authentication ResendContactVerification policy' => [AdminControllers\Authentication\Handlers\ResendContactVerification::class, AuthenticationDenied::class];
        yield 'admin Identity RequestContactVerification policy' => [AdminControllers\Identity\Handlers\RequestContactVerification::class, AuthenticationDenied::class];
        yield 'admin Identity ConfirmContactVerification policy' => [AdminControllers\Identity\Handlers\ConfirmContactVerification::class, AuthenticationDenied::class];
        yield 'admin Identity ResendContactVerification policy' => [AdminControllers\Identity\Handlers\ResendContactVerification::class, AuthenticationDenied::class];
        yield 'InviteAdmin policy' => [AdminControllers\Administration\Handlers\InviteAdmin::class, RuntimeException::class];
        yield 'ApproveAdmin policy' => [AdminControllers\Administration\Handlers\ApproveAdmin::class, RuntimeException::class];
        yield 'ChangeActor policy' => [AdminControllers\Administration\Handlers\ChangeActor::class, RuntimeException::class];
        yield 'history principal' => [AdminControllers\Administration\Handlers\ActorHistory::class, AuthenticationDenied::class];
    }

    public function testAuthenticatedHistoryRequiresValidRouteSelectors(): void
    {
        $fixture    = IdentityFixture::create();
        $container  = $fixture->container();
        $controller = $container->get(AdminControllers\Administration\Handlers\ActorHistory::class);
        $id         = IdentityScenario::id();
        $principal  = new Principal(Realm::ADMIN, ['accountId' => $id, 'actorId' => $id, 'sid' => $id, 'jti' => $id, 'authVersion' => 1]);
        $request    = new ServerRequest()->withAttribute('identityPrincipal', $principal);
        try {
            $controller($request, new Response(), ['realm' => 'invalid']);
            self::fail('An invalid realm must fail at the route boundary.');
        } catch (InvalidUserInput $exception) {
            self::assertSame('The actor realm is invalid.', $exception->getMessage());
        }

        $this->expectException(InvalidUserInput::class);
        $controller($request, new Response(), ['realm' => 'ADMIN']);
    }

    public function testLifecycleAndAdministrationInputsRequireTheVerifiedPrincipal(): void
    {
        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $input     = $container->get(IdentityHttpInput::class);
        try {
            $input->command(IdentityScenario::id(), 'logout', Realm::USER, new ServerRequest());
            self::fail('A lifecycle command must require a verified principal.');
        } catch (AuthenticationDenied $exception) {
            self::assertSame('Authentication is not permitted.', $exception->getMessage());
        }

        $administration = $container->get(AdministrationHttpInput::class);
        $this->expectException(AuthenticationDenied::class);
        $administration->command(IdentityScenario::id(), 'invite-admin', new ServerRequest());
    }

    public function testVerifiedAdministrationStillRequiresJsonContentType(): void
    {
        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $input     = $container->get(AdministrationHttpInput::class);
        $id        = IdentityScenario::id();
        $principal = new Principal(Realm::ADMIN, ['accountId' => $id, 'actorId' => $id, 'sid' => $id, 'jti' => $id, 'authVersion' => 1]);
        $this->expectException(InvalidUserInput::class);
        $input->command(IdentityScenario::id(), 'invite-admin', new ServerRequest()->withAttribute('identityPrincipal', $principal));
    }
}
