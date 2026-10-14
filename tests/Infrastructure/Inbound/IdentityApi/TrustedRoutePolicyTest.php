<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Infrastructure\Inbound\IdentityApi\Controllers;
use Backendbase\Infrastructure\Inbound\IdentityApi\Http\AdministrationHttpInput;
use Backendbase\Infrastructure\Inbound\IdentityApi\Http\IdentityHttpInput;
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
        $controller = $container->get('Backendbase\\Infrastructure\\Inbound\\IdentityApi\\Controllers\\' . $controllerName);
        $this->expectException($exception);
        $controller(new ServerRequest(), new Response(), []);
    }

    /** @return iterable<string, array{string, class-string<Throwable>}> */
    public static function missingPolicies(): iterable
    {
        yield 'authentication policy' => ['Authentication', RuntimeException::class];
        yield 'administration policy' => ['Administration', RuntimeException::class];
        yield 'identity principal' => ['CurrentIdentity', AuthenticationDenied::class];
        yield 'history principal' => ['ActorHistory', AuthenticationDenied::class];
    }

    public function testAuthenticatedHistoryRequiresValidRouteSelectors(): void
    {
        $fixture    = IdentityFixture::create();
        $container  = $fixture->container();
        $controller = $container->get(Controllers\ActorHistory::class);
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
