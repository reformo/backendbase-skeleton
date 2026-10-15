<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Services;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineIdentityTransaction;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\IdentityAccount;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\IdentitySession;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountMobile;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\UnitOfWork\EntityManagerFactory;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\TestCase;
use Tests\Support\Services\IsolatedMySql;

use function array_map;
use function array_unique;
use function getenv;
use function spl_object_id;

final class MySqlConcurrencyTest extends TestCase
{
    public function testOptimisticVersionRejectsAStaleWriterOnAnotherConnection(): void
    {
        $fixture = self::fixture();
        try {
            $account = self::account();
            $first   = $fixture->manager();
            $first->persist(IdentityAccount::create($account));
            $first->flush();
            $second = $fixture->manager($fixture->anotherConnection());
            $stale  = $second->find(IdentityAccount::class, $account->id());
            self::assertInstanceOf(IdentityAccount::class, $stale);
            $record = $first->find(IdentityAccount::class, $account->id());
            self::assertInstanceOf(IdentityAccount::class, $record);
            $account->verifyOwnership();
            $record->synchronize($account);
            $first->flush();
            $old = $stale->toDomain();
            $old->close(new DateTimeImmutable('2026-10-14T11:00:00Z'));
            $stale->synchronize($old);
            $this->expectException(OptimisticLockException::class);
            $second->flush();
        } finally {
            $fixture->destroy();
        }
    }

    public function testLockTimeoutRetriesTheWholeWorkWithFreshEntityManagersAndRollsBack(): void
    {
        $fixture              = self::fixture();
        $first                = $fixture->connection();
        $second               = $fixture->anotherConnection();
        $opened               = [];
        $configurationManager = $fixture->manager();
        $session              = new IdentitySession(new EntityManagerFactory($configurationManager->getConfiguration()));
        try {
            $account = self::account();
            $manager = $fixture->manager();
            $manager->persist(IdentityAccount::create($account));
            $manager->flush();
            $first->beginTransaction();
            $first->executeStatement('UPDATE identity_account SET version = version + 1');
            $second->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
            $unit = new DoctrineIdentityTransaction($second, $session);
            try {
                $unit->run(static function () use ($second, $session, &$opened): void {
                    $opened[] = $session->manager();
                    $second->executeStatement('UPDATE identity_account SET version = version + 1');
                });
                self::fail('The held row lock must exhaust the retry limit.');
            } catch (LockWaitTimeoutException) {
                self::assertCount(3, $opened);
                self::assertCount(3, array_unique(array_map(spl_object_id(...), $opened)));
                self::assertFalse($second->isTransactionActive());
            }

            $first->rollBack();
            self::assertSame(1, $second->fetchOne('SELECT version FROM identity_account'));
            $unit->run(static function () use ($second, $session, &$opened): void {
                    $opened[] = $session->manager();
                $second->executeStatement('UPDATE identity_account SET version = version + 1');
            });
            self::assertSame(2, $second->fetchOne('SELECT version FROM identity_account'));
            self::assertCount(4, $opened);
        } finally {
            $second->close();
            $fixture->destroy();
        }
    }

    private static function fixture(): IsolatedMySql
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            self::markTestSkipped('An explicitly prepared MySQL 8.4 target is required.');
        }

        return IsolatedMySql::create();
    }

    private static function account(): Account
    {
        return Account::create(IdentityId::create(), new AccountEmail('race@example.com'), new AccountMobile('+905551234567'), new DateTimeImmutable('2026-10-14T10:00:00Z'));
    }
}
