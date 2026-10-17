<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Services;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Operator\DoctrineOperatorStore;
use Backendbase\Domain\IdentityAndAccess\Application\Operations\OperatorWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\ContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\OperatorInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Contracts\VerificationLimiter;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\CapturedContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\MemoryTokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\MemoryVerificationLimiter;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\BackendbaseAbstractMigration;
use Backendbase\Infrastructure\Configuration\IdentitySettings;
use Backendbase\Migrations\Version20260823000000;
use Backendbase\Migrations\Version20261014020000;
use Backendbase\Migrations\Version20261017000000;
use Backendbase\Migrations\Version20261017010000;
use Backendbase\Migrations\Version20261017173407;
use Backendbase\Shared\Time\Clock;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Configuration;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tests\Infrastructure\Composition\ProductionContainerFixture;
use Tests\Support\Services\IsolatedMySql;
use Tests\Support\Time\FrozenClock;

use function getenv;

final class MySqlOperatorTest extends TestCase
{
    public function testFirstAdminSetupContinuesAfterOperatorHistoryRemovalOnMySql84(): void
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            self::markTestSkipped('An explicitly prepared MySQL 8.4 target is required.');
        }

        $fixture = IsolatedMySql::create(false);
        try {
            $connection = $fixture->connection();
            foreach ([Version20260823000000::class, Version20261014020000::class, Version20261017000000::class, Version20261017010000::class] as $class) {
                $this->apply($connection, new $class($connection, new NullLogger()));
            }

            $manager   = $fixture->manager();
            $clock     = new FrozenClock(new DateTimeImmutable('2026-10-14T10:00:00Z'));
            $container = ProductionContainerFixture::build([
                Connection::class => $connection,
                Configuration::class => $manager->getConfiguration(),
                LoggerInterface::class => new NullLogger(),
                Clock::class => $clock,
                IdentitySettings::class => IdentityFixture::settings(),
                ContactNotifier::class => new CapturedContactNotifier(),
                VerificationLimiter::class => new MemoryVerificationLimiter($clock),
                TokenRegistry::class => new MemoryTokenRegistry($clock),
            ]);
            $identity  = new IdentityFixture($connection, $container);
            $enrolled  = IdentityScenario::verified($identity);
            $workflow  = $container->get(OperatorWorkflow::class);
            $adminId   = $workflow->run(new OperatorInput('invite-first-admin', $enrolled['accountId'], 'operator', 'invite:123', ''), $clock->now(), true);
            $connection->executeStatement("INSERT INTO operator_action (id, target_id, action_name, bootstrap_key, operator_name, invitation_evidence, recorded_at) VALUES (UUID_TO_BIN(UUID(), 0), UUID_TO_BIN(?, 0), 'invite-first-admin', 'invite-first-admin', 'operator', 'invite:123', '2026-10-14 10:00:00')", [$adminId]);
            $this->apply($connection, new Version20261017173407($connection, new NullLogger()));
            $workflow->run(new OperatorInput('approve-first-admin', $adminId, 'operator', '', 'approve:456'), $clock->now(), true);
            $principal = IdentityScenario::principal($identity, Realm::ADMIN, 'ada@example.com');
            self::assertSame($adminId, $principal->identity()['actorId']);
            $schema = $connection->createSchemaManager();
            self::assertNotContains('operator_action', $schema->listTableNames());
            $state = $connection->fetchOne('SELECT state FROM `admin`');
            self::assertIsString($state);
            self::assertJsonStringEqualsJsonString('{"approved":true,"invitation":"invite:123","approval":"approve:456"}', $state);
        } finally {
            $fixture->destroy();
        }
    }

    public function testFirstAdminSetupLockBlocksAnotherConnectionUntilRelease(): void
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            self::markTestSkipped('An explicitly prepared MySQL 8.4 target is required.');
        }

        $fixture    = IsolatedMySql::create(false);
        $connection = $fixture->anotherConnection();
        try {
            $first  = new DoctrineOperatorStore($fixture->connection());
            $second = new DoctrineOperatorStore($connection);
            $first->exclusive(static function () use ($second): void {
                try {
                    $second->exclusive(static fn (): never => self::fail('Concurrent first-Admin setup must not run.'));
                } catch (IdentityConflict $exception) {
                    self::assertStringContainsString('First-Admin setup is busy', $exception->getMessage());
                }
            });
            self::assertSame('released', $second->exclusive(static fn (): string => 'released'));
        } finally {
            $connection->close();
            $fixture->destroy();
        }
    }

    private function apply(Connection $connection, BackendbaseAbstractMigration $migration): void
    {
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }
}
