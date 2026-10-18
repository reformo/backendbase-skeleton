<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application\Registration;

use Backendbase\Domain\IdentityAndAccess\Application\Workflows\RegistrationWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RegistrationInput;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailEvidenceTest extends TestCase
{
    #[DataProvider('optionalEvidence')]
    public function testEmailEvidenceCannotBlockRegistrationOrLogin(string $scenario): void
    {
        $fixture    = IdentityFixture::create();
        $values     = ContactScenario::verified($fixture);
        $connection = $fixture->connection();
        match ($scenario) {
            'absent' => $connection->executeStatement("DELETE FROM contact_verification WHERE contact_type = 'email'"),
            'unverified' => $connection->executeStatement("UPDATE contact_verification SET verified_at = NULL WHERE contact_type = 'email'"),
            'expired' => $connection->executeStatement("UPDATE contact_verification SET verified_at = '2026-10-13 22:01:01.000000' WHERE contact_type = 'email'"),
            'future' => $connection->executeStatement("UPDATE contact_verification SET verified_at = '2099-01-01 00:00:00.000000' WHERE contact_type = 'email'"),
            'consumed' => $connection->executeStatement("UPDATE contact_verification SET state = json_set(state, '$.consumedAt', '2026-10-14 10:01:01.000000') WHERE contact_type = 'email'"),
            default => $values['email'] = 'other@example.com',
        };
        $container  = $fixture->container();
        $workflow   = $container->get(RegistrationWorkflow::class);
        $registered = $workflow->register(IdentityScenario::id(), new RegistrationInput(...$values));
        $principal  = IdentityScenario::principal($fixture, identifier: $values['email']);
        self::assertSame($registered['actorId'], $principal->identity()['actorId']);
        self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM contact_verification WHERE account_id IS NOT NULL'));
        self::assertSame('mobile', $connection->fetchOne('SELECT contact_type FROM contact_verification WHERE account_id IS NOT NULL'));
        self::assertSame($values['email'], $connection->fetchOne('SELECT normalized_email FROM account'));
    }

    /** @return iterable<string, array{string}> */
    public static function optionalEvidence(): iterable
    {
        foreach (['absent', 'unverified', 'expired', 'future', 'consumed', 'different email'] as $scenario) {
            yield $scenario => [$scenario];
        }
    }
}
