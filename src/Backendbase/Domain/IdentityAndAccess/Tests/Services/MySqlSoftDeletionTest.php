<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Services;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\Services\IdentityServiceFixture;
use Tests\Support\Services\IsolatedMySql;

use function getenv;

final class MySqlSoftDeletionTest extends TestCase
{
    public function testNativeActiveLoginUniquenessRejectsRestorationAndRollsBack(): void
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            self::markTestSkipped('An explicitly prepared MySQL 8.4 target is required.');
        }

        $fixture = IsolatedMySql::create();
        try {
            $connection = $fixture->connection();
            $identity   = IdentityServiceFixture::create($connection);
            $first      = IdentityScenario::registered($identity);
            $container  = $identity->container();
            $store      = $container->get(IdentityStore::class);
            $store->transaction(static function () use ($store, $first): void {
                $store->accountForUpdate($first['accountId']);
                $actor = $store->actorForUpdate(Realm::USER, $first['actorId']);
                $actor->remove(new DateTimeImmutable('2026-10-14T10:01:00Z'));
                $store->saveActor($actor);
            });
            $second = IdentityScenario::registered($identity, 'ADA.USER', 'second@example.com');
            try {
                $store->transaction(static function () use ($store, $first): void {
                    $store->accountForUpdate($first['accountId']);
                    $actor = $store->actorForUpdate(Realm::USER, $first['actorId']);
                    $actor->restore();
                    $store->saveActor($actor);
                });
                self::fail('The generated active key must reject restoration over a reused login.');
            } catch (IdentityConflict $exception) {
                self::assertNotNull($exception->getPrevious());
            }

            self::assertSame('2026-10-14 10:01:00.000000', $connection->fetchOne('SELECT deleted_at FROM identity_user WHERE id = UUID_TO_BIN(?, 0)', [$first['actorId']]));
            self::assertNull($connection->fetchOne('SELECT active_login_name FROM identity_user WHERE id = UUID_TO_BIN(?, 0)', [$first['actorId']]));
            self::assertSame($second['actorId'], $connection->fetchOne("SELECT uuid FROM identity_user WHERE active_login_name = 'ada.user'"));
            self::assertSame(2, $connection->fetchOne('SELECT COUNT(*) FROM identity_user'));
            self::assertFalse($connection->isTransactionActive());
        } finally {
            $fixture->destroy();
        }
    }
}
