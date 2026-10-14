<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Services;

use Backendbase\Domain\IdentityAndAccess\Application\Operations\OperatorWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\AuthenticationSecrets;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\OperatorInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\OwnershipNotifier;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\CapturedOwnershipNotifier;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\MemoryTokenRegistry;
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
    public function testBootstrapAndRecoveryAuditAreAtomicOnMySql84(): void
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
                OwnershipNotifier::class => new CapturedOwnershipNotifier(),
                TokenRegistry::class => new MemoryTokenRegistry($clock),
            ]);
            $identity  = new IdentityFixture($connection, $container);
            $enrolled  = IdentityScenario::enrolled($identity);
            $workflow  = $container->get(OperatorWorkflow::class);
            $secrets   = $container->get(AuthenticationSecrets::class);
            $adminId   = $workflow->run(new OperatorInput('invite-first-admin', $enrolled['accountId'], 'operator', 'invite:123', '', 'first.admin'), $secrets, $clock->now(), true);
            $workflow->run(new OperatorInput('approve-first-admin', $adminId, 'operator', '', 'approve:456'), $secrets, $clock->now(), true);
            $principal = IdentityScenario::principal($identity, $enrolled['secret'], Realm::ADMIN, 'first.admin');
            self::assertSame($adminId, $principal->identity()['actorId']);
            $workflow->run(new OperatorInput('recover-totp', $enrolled['accountId'], 'operator', 'request:123', 'approve:789'), $secrets, $clock->now(), true);
            self::assertSame(3, $connection->fetchOne('SELECT COUNT(*) FROM identity_operator_action'));
            self::assertSame($adminId, $connection->fetchOne("SELECT BIN_TO_UUID(target_id, 0) FROM identity_operator_action WHERE action_name = 'approve-first-admin'"));
            self::assertSame('false', $connection->fetchOne("SELECT JSON_EXTRACT(state, '$.owned') FROM identity_account"));
        } finally {
            $fixture->destroy();
        }
    }
}
