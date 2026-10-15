<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Services;

use Backendbase\Domain\IdentityAndAccess\Application\Operations\OperatorWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\ContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\OperatorInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Contracts\VerificationLimiter;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\CapturedContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\MemoryTokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\MemoryVerificationLimiter;
use Backendbase\Infrastructure\Configuration\IdentitySettings;
use Backendbase\Migrations\Version20261014020000;
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
    public function testBootstrapAuditIsAtomicOnMySql84(): void
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            self::markTestSkipped('An explicitly prepared MySQL 8.4 target is required.');
        }

        $fixture = IsolatedMySql::create(false);
        try {
            $connection = $fixture->connection();
            $migration  = new Version20261014020000($connection, new NullLogger());
            $migration->up(new Schema());
            foreach ($migration->getSql() as $query) {
                $connection->executeStatement($query->getStatement());
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
            $workflow->run(new OperatorInput('approve-first-admin', $adminId, 'operator', '', 'approve:456'), $clock->now(), true);
            $principal = IdentityScenario::principal($identity, Realm::ADMIN, 'ada@example.com');
            self::assertSame($adminId, $principal->identity()['actorId']);
            self::assertSame(2, $connection->fetchOne('SELECT COUNT(*) FROM identity_operator_action'));
            self::assertSame($adminId, $connection->fetchOne("SELECT BIN_TO_UUID(target_id, 0) FROM identity_operator_action WHERE action_name = 'approve-first-admin'"));
        } finally {
            $fixture->destroy();
        }
    }
}
