<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application\Registration;

use Backendbase\Domain\IdentityAndAccess\Application\Workflows\RegistrationWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RegistrationInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Registration\RegistrationPolicy;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Shared\Domain\Exception\DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequiredEvidenceTest extends TestCase
{
    #[DataProvider('invalidEmailEvidence')]
    public function testRequiredEmailRejectsUnusableEvidenceBeforeIdentityWrites(string $scenario, bool $requireMobile): void
    {
        $fixture    = IdentityFixture::create([RegistrationPolicy::class => new RegistrationPolicy($requireMobile, true)]);
        $values     = ContactScenario::verified($fixture);
        $connection = $fixture->connection();
        match ($scenario) {
            'missing' => $connection->executeStatement("DELETE FROM contact_verification WHERE contact_type = 'email'"),
            'unverified' => $connection->executeStatement("UPDATE contact_verification SET verified_at = NULL WHERE contact_type = 'email'"),
            'expired' => $connection->executeStatement("UPDATE contact_verification SET verified_at = '2026-10-13 22:01:01.000000' WHERE contact_type = 'email'"),
            'future' => $connection->executeStatement("UPDATE contact_verification SET verified_at = '2099-01-01 00:00:00.000000' WHERE contact_type = 'email'"),
            'consumed' => $connection->executeStatement("UPDATE contact_verification SET state = json_set(state, '$.consumedAt', '2026-10-14 10:01:01.000000') WHERE contact_type = 'email'"),
            'wrong realm' => $connection->executeStatement("UPDATE contact_verification SET realm = 'ADMIN' WHERE contact_type = 'email'"),
            'wrong purpose' => $connection->executeStatement("UPDATE contact_verification SET purpose = 'user-contact-verification' WHERE contact_type = 'email'"),
            'wrong context' => $values['verificationContextToken'] = ContactScenario::token(),
            default => $values['email']                            = 'another@example.com',
        };
        $container = $fixture->container();
        $workflow  = $container->get(RegistrationWorkflow::class);
        try {
            $workflow->register(IdentityScenario::id(), new RegistrationInput(...$values));
            self::fail('Required email evidence must be usable.');
        } catch (DomainException) {
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM account'));
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM user'));
            self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM contact_verification WHERE account_id IS NOT NULL'));
        }
    }

    /** @return iterable<string, array{string, bool}> */
    public static function invalidEmailEvidence(): iterable
    {
        foreach (['missing', 'unverified', 'expired', 'future', 'consumed', 'wrong realm', 'wrong purpose', 'wrong context', 'different email'] as $scenario) {
            yield $scenario . ' email only' => [$scenario, false];
            yield $scenario . ' both' => [$scenario, true];
        }
    }
}
