<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\ExampleApi\Security;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationGate;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\CQRS\QueryBus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Infrastructure\Inbound\ExampleApi\ExampleApiFixture;
use Tests\Infrastructure\Inbound\IdentityApi\IdentityApiFixture;

final class ExampleAuthorizationTest extends TestCase
{
    #[DataProvider('protectedRoutes')]
    public function testProtectedRoutesRejectMissingInvalidCrossRealmRevokedAndExpiredTokens(string $method, string $path): void
    {
        $commands = $this->createMock(CommandBus::class);
        $commands->expects(self::never())->method('handle');
        $queries = $this->createMock(QueryBus::class);
        $queries->expects(self::never())->method('handle');
        $identity = ExampleApiFixture::identity([CommandBus::class => $commands, QueryBus::class => $queries]);
        IdentityScenario::registered($identity);
        IdentityScenario::admin($identity);
        $token      = ExampleApiFixture::token($identity);
        $adminToken = ExampleApiFixture::token($identity, Realm::ADMIN, 'admin@example.com');
        $api        = IdentityApiFixture::example($identity);

        foreach ([null, 'invalid', $token . 'tampered', $adminToken] as $deniedToken) {
            $response = $api->request($method, $path, token: $deniedToken);
            self::assertSame(401, $response->getStatusCode());
        }

        $container = $identity->container();
        $gate      = $container->get(AuthenticationGate::class);
        $registry  = $container->get(TokenRegistry::class);
        $registry->revokeToken($gate->verify(Realm::USER, $token));
        self::assertSame(401, $api->request($method, $path, token: $token)->getStatusCode());
        $expired = ExampleApiFixture::token($identity);
        IdentityScenario::advance($identity, 'PT16M');
        self::assertSame(401, $api->request($method, $path, token: $expired)->getStatusCode());
    }

    #[DataProvider('protectedRoutes')]
    public function testAuthenticationDependencyFailurePreventsProtectedWork(string $method, string $path): void
    {
        $registry = $this->createStub(TokenRegistry::class);
        $registry->method('assertActive')->willThrowException(AuthenticationUnavailable::create('Authentication state is unavailable.'));
        $commands = $this->createMock(CommandBus::class);
        $commands->expects(self::never())->method('handle');
        $queries = $this->createMock(QueryBus::class);
        $queries->expects(self::never())->method('handle');
        $identity = ExampleApiFixture::identity([TokenRegistry::class => $registry, CommandBus::class => $commands, QueryBus::class => $queries]);
        IdentityScenario::registered($identity);
        $token = ExampleApiFixture::token($identity);
        $api   = IdentityApiFixture::example($identity);
        self::assertSame(503, $api->request($method, $path, token: $token)->getStatusCode());
    }

    /** @return iterable<string, array{string, string}> */
    public static function protectedRoutes(): iterable
    {
        yield 'groups' => ['GET', '/example-types/system/groups'];
        yield 'examples' => ['GET', '/example-types/system/groups/settings/examples'];
        yield 'create' => ['POST', '/example-types/system/groups/settings/examples'];
        yield 'update' => ['PATCH', '/example-types/system/groups/settings/examples/page-size'];
        yield 'remove' => ['DELETE', '/example-types/system/groups/settings/examples/page-size'];
        yield 'contact request' => ['POST', '/identity/contact-verifications'];
        yield 'contact confirm' => ['POST', '/identity/contact-verifications/' . IdentityScenario::id() . '/confirm'];
        yield 'contact resend' => ['POST', '/identity/contact-verifications/' . IdentityScenario::id() . '/resend'];
        yield 'identity' => ['GET', '/identity'];
        yield 'revoke token' => ['DELETE', '/identity/token'];
        yield 'end session' => ['DELETE', '/identity/session'];
        yield 'close account' => ['DELETE', '/identity/account'];
    }
}
