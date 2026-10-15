<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Services;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountMobile;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\Services\IdentityServiceFixture;
use Tests\Support\Services\IsolatedMySql;

use function getenv;

final class MySqlSoftDeletionTest extends TestCase
{
    public function testNativeMobileUniquenessAndReuseAfterAccountClosure(): void
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
            $duplicate  = Account::create(IdentityId::create(), new AccountEmail('second@example.com'), new AccountMobile('+905551234567'), new DateTimeImmutable());
            try {
                $store->transaction(static fn () => $store->saveAccount($duplicate));
                self::fail('The generated active key must reject a duplicate mobile.');
            } catch (IdentityConflict $exception) {
                self::assertNotNull($exception->getPrevious());
            }

            self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM identity_account'));
            $store->transaction(static function () use ($store, $first): void {
                $account = $store->accountForUpdate($first['accountId']);
                $account->close(new DateTimeImmutable('2026-10-14T10:01:00Z'));
                $store->saveAccount($account);
            });
            $second = IdentityScenario::registered($identity);
            self::assertNull($connection->fetchOne('SELECT active_mobile FROM identity_account WHERE id = UUID_TO_BIN(?, 0)', [$first['accountId']]));
            self::assertSame($second['accountId'], $connection->fetchOne("SELECT uuid FROM identity_account WHERE active_mobile = '+905551234567'"));
            self::assertSame(2, $connection->fetchOne('SELECT COUNT(*) FROM identity_account'));
            self::assertFalse($connection->isTransactionActive());
        } finally {
            $fixture->destroy();
        }
    }
}
