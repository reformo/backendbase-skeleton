<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\Workflows\RegistrationWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\ContactStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RegistrationInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactVerificationExpired;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Shared\Domain\Exception\DomainException;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContactRegistrationTest extends TestCase
{
    #[DataProvider('rejectedEvidence')]
    public function testRegistrationRejectsIncompleteOrInvalidEvidenceWithoutCreatingAnIdentity(string $scenario): void
    {
        $fixture    = IdentityFixture::create();
        $values     = ContactScenario::verified($fixture);
        $connection = $fixture->connection();
        match ($scenario) {
            'email mismatch' => $values['email']                   = 'other@example.com',
            'mobile mismatch' => $values['mobile']                 = '+905551234568',
            'wrong context' => $values['verificationContextToken'] = ContactScenario::token(),
            'same row' => $values['mobileContactInfoId']           = $values['emailContactInfoId'],
            'unknown row' => $values['emailContactInfoId']         = IdentityScenario::id(),
            'unverified' => $connection->executeStatement('UPDATE contact_verification SET verified_at = NULL WHERE contact_type = ?', ['mobile']),
            'future' => $connection->executeStatement("UPDATE contact_verification SET verified_at = '2099-01-01 00:00:00.000000'"),
            'wrong purpose' => $connection->executeStatement("UPDATE contact_verification SET purpose = 'admin-contact-reverification'"),
            'wrong realm' => $connection->executeStatement("UPDATE contact_verification SET realm = 'ADMIN'"),
            default => IdentityScenario::advance($fixture, $scenario),
        };
        $container = $fixture->container();
        $workflow  = $container->get(RegistrationWorkflow::class);
        try {
            $workflow->register(IdentityScenario::id(), new RegistrationInput(...$values));
            self::fail('Registration must require both fresh matching contact proofs.');
        } catch (DomainException) {
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM account'));
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM user'));
            self::assertStringNotContainsString('consumedAt":"20', (string) $connection->fetchOne('SELECT state FROM contact_verification LIMIT 1'));
        }
    }

    /** @return iterable<string, array{string}> */
    public static function rejectedEvidence(): iterable
    {
        foreach (['email mismatch', 'mobile mismatch', 'wrong context', 'same row', 'unknown row', 'unverified', 'future', 'wrong purpose', 'wrong realm', 'PT12H', 'PT12H1S'] as $scenario) {
            yield $scenario => [$scenario];
        }
    }

    public function testRegistrationConsumesBothProofsOnceAndLoginStillWorksAfterTwelveHours(): void
    {
        $fixture = IdentityFixture::create();
        $values  = ContactScenario::verified($fixture);
        IdentityScenario::advance($fixture, 'PT11H59M59S');
        $container  = $fixture->container();
        $workflow   = $container->get(RegistrationWorkflow::class);
        $registered = $workflow->register(IdentityScenario::id(), new RegistrationInput(...$values));
        IdentityScenario::advance($fixture, 'PT13H');
        $principal = IdentityScenario::principal($fixture);
        self::assertSame($registered['actorId'], $principal->identity()['actorId']);
        try {
            $workflow->register(IdentityScenario::id(), new RegistrationInput(...$values));
            self::fail('Consumed verification cannot register another Account.');
        } catch (DomainException $exception) {
            self::assertNotInstanceOf(ContactVerificationExpired::class, $exception);
            self::assertSame(1, $fixture->connection()->fetchOne('SELECT COUNT(*) FROM account'));
        }
    }

    public function testTransactionRollbackPreservesBothUsableProofs(): void
    {
        $fixture   = IdentityFixture::create();
        $values    = ContactScenario::verified($fixture);
        $container = $fixture->container();
        $store     = $container->get(ContactStore::class);
        try {
            $store->transaction(static function () use ($store, $values): void {
                $first               = $store->get($values['emailContactInfoId']);
                $state               = $first->snapshot();
                $state['consumedAt'] = '2026-10-14 10:02:00.000000';
                $store->save(new ContactInfo(IdentityId::fromString($first->id()), $state));

                throw new RuntimeException('Forced transaction failure.');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('Forced transaction failure.', $exception->getMessage());
        }

        $workflow = $container->get(RegistrationWorkflow::class);
        self::assertArrayHasKey('accountId', $workflow->register(IdentityScenario::id(), new RegistrationInput(...$values)));
    }

    public function testFailedAccountFlushRollsBackAllRegistrationWritesAndBothProofsRemainUsable(): void
    {
        $fixture    = IdentityFixture::create();
        $values     = ContactScenario::verified($fixture);
        $connection = $fixture->connection();
        $connection->executeStatement("CREATE TRIGGER fail_registration BEFORE INSERT ON user BEGIN SELECT RAISE(ABORT, 'Forced registration failure'); END");
        $container = $fixture->container();
        $workflow  = $container->get(RegistrationWorkflow::class);
        try {
            $workflow->register(IdentityScenario::id(), new RegistrationInput(...$values));
            self::fail('The entire registration must roll back.');
        } catch (Exception $exception) {
            self::assertStringContainsString('Forced registration failure', $exception->getMessage());
        }

        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM account'));
        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM user'));
        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM contact_verification WHERE account_id IS NOT NULL OR actor_id IS NOT NULL'));
        self::assertSame(2, $connection->fetchOne("SELECT COUNT(*) FROM contact_verification WHERE verified_at IS NOT NULL AND json_extract(state, '$.consumedAt') IS NULL"));
        $connection->executeStatement('DROP TRIGGER fail_registration');
        self::assertArrayHasKey('accountId', $workflow->register(IdentityScenario::id(), new RegistrationInput(...$values)));
    }
}
