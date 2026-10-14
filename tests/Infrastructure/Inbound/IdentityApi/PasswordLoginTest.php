<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\IdentityApi;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class PasswordLoginTest extends TestCase
{
    #[DataProvider('deniedAccounts')]
    public function testPasswordLoginPreservesAccountAndActorGates(string $state): void
    {
        $fixture   = IdentityFixture::create();
        $user      = $state === 'unverified' ? IdentityScenario::registered($fixture) : IdentityScenario::verified($fixture);
        $container = $fixture->container();
        $store     = $container->get(IdentityStore::class);
        $store->transaction(static function () use ($store, $user, $state): void {
            if ($state === 'closed') {
                $account = $store->accountForUpdate($user['accountId']);
                $account->close(new DateTimeImmutable('2026-10-15T10:00:00Z'));
                $store->saveAccount($account);
            }

            if ($state !== 'removed') {
                return;
            }

            $actor = $store->actorForUpdate(Realm::USER, $user['actorId']);
            $actor->remove(new DateTimeImmutable('2026-10-15T10:00:00Z'));
            $store->saveActor($actor);
        });
        $api      = IdentityApiFixture::create($fixture, Realm::USER);
        $password = $state === 'wrong-password' ? 'wrong-password-123' : 'valid-password-123';
        $response = $api->request('POST', '/auth/login', ['loginName' => 'ada.user', 'password' => $password]);
        self::assertSame(401, $response->getStatusCode());
        self::assertStringNotContainsString('accessToken', (string) $response->getBody());
        $connection = $fixture->connection();
        self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM identity_challenge'));
    }

    /** @return iterable<string, array{string}> */
    public static function deniedAccounts(): iterable
    {
        yield 'ownership required' => ['unverified'];
        yield 'password required' => ['wrong-password'];
        yield 'Account active' => ['closed'];
        yield 'actor active' => ['removed'];
    }

    public function testExistingCredentialsRemainReadableAndPasswordLoginDoesNotCreateChallenges(): void
    {
        $fixture    = IdentityFixture::create();
        $user       = IdentityScenario::verified($fixture);
        $connection = $fixture->connection();
        $stored     = $connection->fetchOne('SELECT credentials FROM identity_account');
        self::assertIsString($stored);
        $credentials = json_decode($stored, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($credentials);
        self::assertCount(1, $credentials);
        $legacy = [...$credentials, 'totpSecret' => 'retired-key:ciphertext', 'pendingTotpSecret' => null, 'lastTotpStep' => 1, 'failedMfaAttempts' => 5, 'blockedUntil' => '2099-01-01 00:00:00.000000'];
        $connection->executeStatement('UPDATE identity_account SET credentials = ? WHERE uuid = ?', [json_encode($legacy, JSON_THROW_ON_ERROR), $user['accountId']]);
        $api      = IdentityApiFixture::create($fixture, Realm::USER);
        $response = $api->request('POST', '/auth/login', ['loginName' => 'ada.user', 'password' => 'valid-password-123']);
        self::assertSame(200, $response->getStatusCode());
        self::assertNotEmpty(UserFlowTest::resource($response, 'session')['accessToken']);
        self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM identity_challenge'));
    }

    #[DataProvider('retiredChallenges')]
    public function testRetiredChallengesCannotVerifyOwnership(string $purpose): void
    {
        $fixture    = IdentityFixture::create();
        $registered = IdentityScenario::registered($fixture);
        $connection = $fixture->connection();
        $connection->executeStatement('UPDATE identity_challenge SET purpose = ? WHERE uuid = ?', [$purpose, $registered['challengeId']]);
        $api      = IdentityApiFixture::create($fixture, Realm::USER);
        $response = $api->request('POST', '/auth/ownership/verify', ['challengeId' => $registered['challengeId'], 'code' => '123456']);
        self::assertSame(401, $response->getStatusCode());
    }

    /** @return iterable<string, array{string}> */
    public static function retiredChallenges(): iterable
    {
        yield 'login challenge' => ['login'];
        yield 'factor enrollment' => ['totp-enrollment'];
    }
}
