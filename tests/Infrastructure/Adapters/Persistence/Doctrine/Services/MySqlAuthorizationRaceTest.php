<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Persistence\Doctrine\Services;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationGate;
use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationTokens;
use Backendbase\Domain\IdentityAndAccess\Application\Workflows\AdministrationWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\ContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Administration\ActorChangeInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Contracts\VerificationLimiter;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Actor;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\CapturedContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\MemoryTokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\MemoryVerificationLimiter;
use Backendbase\Infrastructure\Configuration\IdentitySettings;
use Backendbase\Shared\Time\Clock;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tests\Infrastructure\Composition\ProductionContainerFixture;
use Tests\Support\Services\IsolatedMySql;
use Tests\Support\Time\FrozenClock;

use function getenv;

final class MySqlAuthorizationRaceTest extends TestCase
{
    public function testRemovedAdminCannotRestoreItselfFromAStaleReference(): void
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            self::markTestSkipped('An explicitly prepared MySQL 8.4 target is required.');
        }

        $fixture = IsolatedMySql::create();
        $other   = $fixture->anotherConnection();
        try {
            $identity  = self::identity($fixture);
            $admin     = IdentityScenario::admin($identity);
            $principal = IdentityScenario::principal($identity, Realm::ADMIN, 'admin@example.com');
            $container = $identity->container();
            $realStore = $container->get(IdentityStore::class);
            $store     = $this->createMock(IdentityStore::class);
            $store->method('transaction')->willReturnCallback($realStore->transaction(...));
            $store->method('protectActorMutation')->willReturnCallback($realStore->protectActorMutation(...));
            $store->method('account')->willReturnCallback($realStore->account(...));
            $store->method('actor')->willReturnCallback($realStore->actor(...));
            $store->expects(self::never())->method('actorForUpdate');
            $store->expects(self::never())->method('saveActor');
            $store->method('actorReference')->willReturnCallback(static function (Realm $realm, string $id) use ($realStore, $other, $admin): Actor {
                $reference = $realStore->actorReference($realm, $id);
                $other->transactional(static function (Connection $connection) use ($admin): void {
                    $connection->fetchOne('SELECT id FROM identity_account WHERE id = UUID_TO_BIN(?, 0) FOR UPDATE', [$admin['accountId']]);
                    $connection->executeStatement('UPDATE identity_admin SET deleted_at = ?, version = version + 1 WHERE id = UUID_TO_BIN(?, 0)', ['2026-10-14 10:02:00.000000', $admin['actorId']]);
                });

                return $reference;
            });
            $gate     = new AuthenticationGate($store, $container->get(AuthenticationTokens::class));
            $workflow = new AdministrationWorkflow($store, $gate);
            try {
                $workflow->changeActor($principal, new ActorChangeInput($admin['actorId'], Realm::ADMIN, 'restore'), new DateTimeImmutable('2026-10-14T10:03:00Z'));
                self::fail('A concurrently removed Admin must lose mutation authority.');
            } catch (AuthenticationDenied) {
                $connection = $fixture->connection();
                self::assertSame('2026-10-14 10:02:00.000000', $connection->fetchOne('SELECT deleted_at FROM identity_admin WHERE id = UUID_TO_BIN(?, 0)', [$admin['actorId']]));
                self::assertNotNull($connection->fetchOne('SELECT deleted_at FROM identity_admin WHERE id = UUID_TO_BIN(?, 0)', [$admin['actorId']]));
            }
        } finally {
            $other->close();
            $fixture->destroy();
        }
    }

    private static function identity(IsolatedMySql $fixture): IdentityFixture
    {
        $connection = $fixture->connection();
        $manager    = $fixture->manager();
        $clock      = new FrozenClock(new DateTimeImmutable('2026-10-14T10:00:00Z'));
        $container  = ProductionContainerFixture::build([
            Connection::class => $connection,
            Configuration::class => $manager->getConfiguration(),
            LoggerInterface::class => new NullLogger(),
            Clock::class => $clock,
            IdentitySettings::class => IdentityFixture::settings(),
            ContactNotifier::class => new CapturedContactNotifier(),
            VerificationLimiter::class => new MemoryVerificationLimiter($clock),
            TokenRegistry::class => new MemoryTokenRegistry($clock),
        ]);

        return new IdentityFixture($connection, $container);
    }
}
