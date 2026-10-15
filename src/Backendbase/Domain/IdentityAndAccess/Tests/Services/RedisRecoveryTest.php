<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Services;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\RedisTokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationGate;
use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationTokens;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenCodec;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Services\IsolatedRedis;

final class RedisRecoveryTest extends TestCase
{
    #[DataProvider('realms')]
    public function testLostAndPartiallyRestoredAllowlistStateRejectsAuthentication(Realm $realm): void
    {
        $principal = new Principal($realm, ['accountId' => IdentityScenario::id(), 'actorId' => IdentityScenario::id(), 'jti' => IdentityScenario::id(), 'sid' => IdentityScenario::id(), 'authVersion' => 1]);
        $identity  = $principal->identity();
        $fixture   = IsolatedRedis::create($identity['accountId']);
        try {
            $client   = $fixture->client();
            $registry = new RedisTokenRegistry($fixture->json());
            $registry->activate($principal, new DateTimeImmutable('+15 minutes'));
            [$token, $session] = $fixture->keys($principal);
            $snapshot          = $client->dump($token);
            self::assertIsString($snapshot);
            $client->del($token, $session);
            self::assertDenied(static fn () => $registry->assertActive($principal));
            self::assertTrue($client->restore($token, 900000, $snapshot));
            self::assertDenied(static fn () => $registry->assertActive($principal));
            $client->close();
            self::assertDenied(static fn () => $registry->assertActive($principal));
        } finally {
            $fixture->destroy();
        }
    }

    #[DataProvider('realms')]
    public function testRestoredOldRedisRecordsCannotBypassCurrentAccountClosure(Realm $realm): void
    {
        $identity  = IdentityFixture::create();
        $enrolled  = IdentityScenario::admin($identity);
        $principal = IdentityScenario::principal($identity, $realm, 'admin@example.com');
        $fixture   = IsolatedRedis::create($enrolled['accountId']);
        try {
            $client    = $fixture->client();
            $registry  = new RedisTokenRegistry($fixture->json());
            $container = $identity->container();
            $tokens    = new AuthenticationTokens($container->get(TokenCodec::class), $registry);
            $store     = $container->get(IdentityStore::class);
            $gate      = new AuthenticationGate($store, $tokens);
            $token     = $tokens->issue($principal, new DateTimeImmutable('+15 minutes'));
            self::assertSame($principal->claims(), $gate->verify($realm, $token)->claims());
            [$tokenKey, $sessionKey, $blocked] = $fixture->keys($principal);
            $tokenSnapshot                     = $client->dump($tokenKey);
            $sessionSnapshot                   = $client->dump($sessionKey);
            self::assertIsString($tokenSnapshot);
            self::assertIsString($sessionSnapshot);
            $store->transaction(static function () use ($store, $enrolled): void {
                $account = $store->accountForUpdate($enrolled['accountId']);
                $account->close(new DateTimeImmutable('2026-10-14T10:01:00Z'));
                $store->saveAccount($account);
            });
            $registry->revokeAccount($enrolled['accountId']);
            $client->del($tokenKey, $sessionKey, $blocked);
            self::assertTrue($client->restore($tokenKey, 900000, $tokenSnapshot));
            self::assertTrue($client->restore($sessionKey, 900000, $sessionSnapshot));
            $registry->assertActive($principal);
            self::assertDenied(static fn () => $gate->verify($realm, $token));
        } finally {
            $fixture->destroy();
        }
    }

    /** @return iterable<string, array{Realm}> */
    public static function realms(): iterable
    {
        yield 'USER' => [Realm::USER];
        yield 'ADMIN' => [Realm::ADMIN];
    }

    private static function assertDenied(callable $work): void
    {
        try {
            $work();
            self::fail('Recovered authentication state must preserve the rejection.');
        } catch (AuthenticationDenied $exception) {
            self::assertSame('Authentication is not permitted.', $exception->getMessage());
        }
    }
}
