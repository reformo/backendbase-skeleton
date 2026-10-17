<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Persistence;

use Backendbase\Domain\IdentityAndAccess\Application\Credentials;
use Backendbase\Domain\IdentityAndAccess\Contracts\CredentialStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DoctrineCredentialStoreTest extends TestCase
{
    public function testReplacingThePasswordHashRejectsThePreviousPassword(): void
    {
        $fixture     = IdentityFixture::create();
        $registered  = IdentityScenario::registered($fixture);
        $container   = $fixture->container();
        $store       = $container->get(IdentityStore::class);
        $credentials = $container->get(Credentials::class);
        $password    = new Password('replacement-password-123');
        $hash        = $credentials->hashPassword($password);
        $store->transaction(static fn () => $credentials->initialize($registered['accountId'], $hash));
        $connection = $fixture->connection();
        self::assertSame($hash, $connection->fetchOne('SELECT password_hash FROM identity_account'));
        $store->transaction(static fn () => $credentials->assertPassword($registered['accountId'], $password));
        $this->expectException(AuthenticationDenied::class);
        $store->transaction(static fn () => $credentials->assertPassword($registered['accountId'], new Password('valid-password-123')));
    }

    public function testAccountClosurePreservesTheHashAndRejectsPasswordReads(): void
    {
        $fixture    = IdentityFixture::create();
        $registered = IdentityScenario::registered($fixture);
        $connection = $fixture->connection();
        $hash       = $connection->fetchOne('SELECT password_hash FROM identity_account');
        self::closeAccount($fixture, $registered['accountId']);
        self::assertSame($hash, $connection->fetchOne('SELECT password_hash FROM identity_account'));
        $container   = $fixture->container();
        $store       = $container->get(IdentityStore::class);
        $credentials = $container->get(CredentialStore::class);
        $this->expectException(AuthenticationDenied::class);
        $store->transaction(static fn (): string => $credentials->read($registered['accountId']));
    }

    public function testClosedAccountsRejectPasswordWrites(): void
    {
        $fixture    = IdentityFixture::create();
        $registered = IdentityScenario::registered($fixture);
        $connection = $fixture->connection();
        $hash       = $connection->fetchOne('SELECT password_hash FROM identity_account');
        self::assertIsString($hash);
        self::closeAccount($fixture, $registered['accountId']);
        $container   = $fixture->container();
        $store       = $container->get(IdentityStore::class);
        $credentials = $container->get(CredentialStore::class);
        $this->expectException(AuthenticationDenied::class);
        $store->transaction(static fn () => $credentials->save($registered['accountId'], $hash));
    }

    private static function closeAccount(IdentityFixture $fixture, string $accountId): void
    {
        $container = $fixture->container();
        $store     = $container->get(IdentityStore::class);
        $store->transaction(static function () use ($store, $accountId): void {
            $account = $store->accountForUpdate($accountId);
            $account->close(new DateTimeImmutable('2026-10-17T10:00:00Z'));
            $store->saveAccount($account);
        });
    }
}
