<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Persistence\State;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Seeders\AdminSeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminSeederStateTest extends TestCase
{
    #[DataProvider('invalidStates')]
    public function testRepeatSeedingRejectsInvalidStateWithoutRepairingIt(string $table, string $expression): void
    {
        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $seeder    = $container->get(AdminSeeder::class);
        $seeder->seed(new Password('initial-password-123'));
        $connection = $fixture->connection();
        $connection->executeStatement('UPDATE ' . $table . ' SET state = ' . $expression);
        $before = $connection->fetchOne('SELECT state FROM ' . $table);
        try {
            $seeder->seed(new Password('replacement-password-123'));
            self::fail('Invalid persisted state must stop repeat seeding.');
        } catch (AuthenticationUnavailable) {
            self::assertSame($before, $connection->fetchOne('SELECT state FROM ' . $table));
            self::assertSame(2, $connection->fetchOne('SELECT COUNT(*) FROM identity_operator_action'));
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidStates(): iterable
    {
        yield 'missing Account version' => ['identity_account', "json_remove(state, '$.authVersion')"];
        yield 'unknown Account field' => ['identity_account', "json_set(state, '$.futureField', 1)"];
        yield 'missing Admin evidence' => ['identity_admin', "json_remove(state, '$.invitation')"];
        yield 'invalid Admin approval type' => ['identity_admin', "json_set(state, '$.approved', 1)"];
    }
}
