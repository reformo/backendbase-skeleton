<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application\Registration;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\IdentitySession;
use Backendbase\Domain\IdentityAndAccess\Application\Workflows\RegistrationWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\AuthenticationSecrets;
use Backendbase\Domain\IdentityAndAccess\Contracts\CredentialStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RegistrationInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;
use Backendbase\Domain\IdentityAndAccess\Domain\Registration\RegistrationPolicy;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CredentialOrderingTest extends TestCase
{
    public function testHashingPrecedesTheTransactionAndCredentialWriteSharesIt(): void
    {
        $secrets     = $this->createMock(AuthenticationSecrets::class);
        $credentials = $this->createMock(CredentialStore::class);
        $fixture     = IdentityFixture::create([
            AuthenticationSecrets::class => $secrets,
            CredentialStore::class => $credentials,
            RegistrationPolicy::class => new RegistrationPolicy(false, false),
        ]);
        $connection  = $fixture->connection();
        $container   = $fixture->container();
        $session     = $container->get(IdentitySession::class);
        $secrets->expects(self::once())->method('hashPassword')->willReturnCallback(static function (Password $password) use ($connection): string {
            self::assertFalse($connection->isTransactionActive());
            self::assertSame('valid-password-123', $password->forHashing());

            return 'prepared-password-hash';
        });
        $credentials->expects(self::once())->method('save')->willReturnCallback(static function (string $accountId, string $hash) use ($connection, $session): void {
            self::assertTrue($connection->isTransactionActive());
            self::assertSame('prepared-password-hash', $hash);
            $manager = $session->manager();
            $manager->flush();
            self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM account'));
            self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM user'));

            throw new RuntimeException('Credential storage failed.');
        });
        $workflow = $container->get(RegistrationWorkflow::class);
        try {
            $workflow->register(IdentityScenario::id(), self::input());
            self::fail('Credential failure must roll back identity creation.');
        } catch (RuntimeException $failure) {
            self::assertSame('Credential storage failed.', $failure->getMessage());
            self::assertFalse($connection->isTransactionActive());
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM account'));
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM user'));
        }
    }

    public function testHashFailureDoesNotOpenTheIdentityTransaction(): void
    {
        $secrets = $this->createMock(AuthenticationSecrets::class);
        $secrets->expects(self::once())->method('hashPassword')->willThrowException(new RuntimeException('Hashing failed.'));
        $store = $this->createMock(IdentityStore::class);
        $store->expects(self::never())->method('transaction');
        $fixture   = IdentityFixture::create([AuthenticationSecrets::class => $secrets, IdentityStore::class => $store]);
        $container = $fixture->container();
        $workflow  = $container->get(RegistrationWorkflow::class);
        $this->expectExceptionMessage('Hashing failed.');
        $workflow->register(IdentityScenario::id(), self::input());
    }

    private static function input(): RegistrationInput
    {
        return new RegistrationInput('ada@example.com', '+905551234567', 'valid-password-123');
    }
}
