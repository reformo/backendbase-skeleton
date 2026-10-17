<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters;

use Backendbase\Domain\IdentityAndAccess\Application\Workflows\LoginWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\LoginInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Seeders\AdminSeeder;
use Doctrine\DBAL\Exception as DatabaseException;
use PHPUnit\Framework\TestCase;

use function password_verify;

final class AdminSeederTest extends TestCase
{
    public function testTheSeedCreatesOneNamedAdminAndRepeatRunsPreserveItsPassword(): void
    {
        $fixture    = IdentityFixture::create();
        $container  = $fixture->container();
        $seeder     = $container->get(AdminSeeder::class);
        $first      = $seeder->seed(new Password('initial-password-123'));
        $connection = $fixture->connection();
        $hash       = $connection->fetchOne('SELECT password_hash FROM account');
        self::assertIsString($hash);
        self::assertTrue(password_verify('initial-password-123', $hash));
        $second = $seeder->seed(new Password('different-password-123'));
        self::assertTrue($first['created']);
        self::assertFalse($second['created']);
        self::assertSame($first['accountId'], $second['accountId']);
        self::assertSame($first['actorId'], $second['actorId']);
        self::assertSame($hash, $connection->fetchOne('SELECT password_hash FROM account'));
        self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM account'));
        self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM `admin`'));
        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM user'));
        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM contact_verification'));
        $schema = $connection->createSchemaManager();
        self::assertNotContains('operator_action', $schema->listTableNames());
        $state = $connection->fetchOne('SELECT state FROM `admin`');
        self::assertIsString($state);
        self::assertJsonStringEqualsJsonString('{"approved":true,"invitation":"seed:initial-admin:invitation","approval":"seed:initial-admin:approval"}', $state);
        self::assertSame(['normalized_email' => AdminSeeder::EMAIL, 'normalized_mobile' => AdminSeeder::MOBILE, 'first_name' => 'Mehmet', 'last_name' => 'Korkmaz'], $connection->fetchAssociative('SELECT normalized_email, normalized_mobile, first_name, last_name FROM account'));
        $login     = $container->get(LoginWorkflow::class);
        $principal = $login->authenticate(Realm::ADMIN, new LoginInput(AdminSeeder::EMAIL, 'initial-password-123'));
        self::assertSame($first['actorId'], $principal->identity()['actorId']);
        $mobile = $login->authenticate(Realm::ADMIN, new LoginInput(AdminSeeder::MOBILE, 'initial-password-123'));
        self::assertSame($first['actorId'], $mobile->identity()['actorId']);
    }

    public function testTheSeederCannotGrantAdminAccessToAnExistingAccount(): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::verified($fixture, AdminSeeder::MOBILE, AdminSeeder::EMAIL);
        $container = $fixture->container();
        $seeder    = $container->get(AdminSeeder::class);
        $this->expectException(IdentityConflict::class);
        $seeder->seed(new Password('initial-password-123'));
    }

    public function testAnyExistingAdminPreventsCreatingAnotherSeededAdmin(): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::admin($fixture);
        $container = $fixture->container();
        $seeder    = $container->get(AdminSeeder::class);
        $this->expectException(IdentityConflict::class);
        $seeder->seed(new Password('initial-password-123'));
    }

    public function testChangedSeededAuthorityIsNotAutomaticallyRepaired(): void
    {
        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $seeder    = $container->get(AdminSeeder::class);
        $seeder->seed(new Password('initial-password-123'));
        $connection = $fixture->connection();
        $connection->executeStatement("UPDATE `admin` SET state = json_set(state, '$.approved', json('false'))");
        $this->expectException(IdentityConflict::class);
        $seeder->seed(new Password('initial-password-123'));
    }

    public function testChangedOwnershipVerificationPreventsARepeatSeed(): void
    {
        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $seeder    = $container->get(AdminSeeder::class);
        $seeder->seed(new Password('initial-password-123'));
        $connection = $fixture->connection();
        $connection->executeStatement("UPDATE account SET state = json_set(state, '$.ownershipVerified', json('false'))");
        $this->expectException(IdentityConflict::class);
        $seeder->seed(new Password('initial-password-123'));
    }

    public function testAdminInsertFailureRollsBackTheAccountAndPermitsAnotherAttempt(): void
    {
        $fixture    = IdentityFixture::create();
        $connection = $fixture->connection();
        $connection->executeStatement("CREATE TRIGGER fail_seed_admin BEFORE INSERT ON `admin` BEGIN SELECT RAISE(ABORT, 'Forced seed Admin failure'); END");
        $container = $fixture->container();
        $seeder    = $container->get(AdminSeeder::class);
        try {
            $seeder->seed(new Password('initial-password-123'));
            self::fail('A partial Admin bootstrap must roll back.');
        } catch (DatabaseException $exception) {
            self::assertStringContainsString('Forced seed Admin failure', $exception->getMessage());
        }

        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM account'));
        self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM `admin`'));
        $connection->executeStatement('DROP TRIGGER fail_seed_admin');
        self::assertTrue($seeder->seed(new Password('initial-password-123'))['created']);
    }
}
