<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\SystemAdmin;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailMobileLoginTest extends TestCase
{
    #[DataProvider('realmIdentifiers')]
    public function testBothIdentifiersAuthenticateTheSameActor(Realm $realm, string $identifier): void
    {
        $fixture  = IdentityFixture::create();
        $identity = IdentityScenario::admin($fixture);
        $api      = IdentityApiFixture::create($fixture, $realm);
        $response = $api->request('POST', '/auth/login', ['identifier' => $identifier, 'password' => 'valid-password-123']);
        self::assertSame(200, $response->getStatusCode());
        $session  = UserFlowTest::resource($response, 'session');
        $token    = UserFlowTest::text($session, 'accessToken');
        $response = $api->request('GET', '/identity', token: $token);
        self::assertSame(200, $response->getStatusCode());
        $current = UserFlowTest::resource($response, $realm === Realm::USER ? 'user' : 'admin');
        self::assertSame($identity['accountId'], $current['accountId']);
        self::assertSame($realm === Realm::USER ? $identity['userActorId'] : $identity['actorId'], $current['actorId']);
        self::assertSame('admin@example.com', $current['email']);
        self::assertSame('+905551234568', $current['mobile']);
        self::assertArrayNotHasKey('loginName', $current);
    }

    /** @return iterable<string, array{Realm, string}> */
    public static function realmIdentifiers(): iterable
    {
        foreach (Realm::cases() as $realm) {
            yield $realm->value . ' email' => [$realm, 'admin@example.com'];
            yield $realm->value . ' uppercase email' => [$realm, 'ADMIN@EXAMPLE.COM'];
            yield $realm->value . ' mobile' => [$realm, '+905551234568'];
        }
    }

    #[DataProvider('userIdentifiers')]
    public function testUserIdentifiersCannotAuthenticateAnAbsentOrUnapprovedAdmin(string $identifier, bool $pendingAdmin): void
    {
        $fixture   = IdentityFixture::create();
        $identity  = IdentityScenario::verified($fixture);
        $api       = IdentityApiFixture::create($fixture, Realm::ADMIN);
        $request   = ['identifier' => $identifier, 'password' => 'valid-password-123'];
        $container = $fixture->container();
        $store     = $container->get(IdentityStore::class);
        if ($pendingAdmin) {
            $store->transaction(static function () use ($store, $identity): void {
                $admin = SystemAdmin::invite(IdentityId::create(), IdentityId::fromString($identity['accountId']), 'invite:123', new DateTimeImmutable());
                $store->saveActor($admin);
            });
        }

        self::assertSame(401, $api->request('POST', '/auth/login', $request)->getStatusCode());
    }

    /** @return iterable<string, array{string, bool}> */
    public static function userIdentifiers(): iterable
    {
        yield 'email without Admin' => ['ada@example.com', false];
        yield 'mobile without Admin' => ['+905551234567', false];
        yield 'email pending Admin' => ['ada@example.com', true];
        yield 'mobile pending Admin' => ['+905551234567', true];
    }
}
