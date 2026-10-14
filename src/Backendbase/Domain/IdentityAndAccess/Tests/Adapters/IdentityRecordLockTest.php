<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\IdentityAccount;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\IdentityRecordStore;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\IdentitySession;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\UnitOfWork\EntityManagerFactory;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Result;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Doctrine\UuidBinaryType;
use Ramsey\Uuid\Uuid;

final class IdentityRecordLockTest extends TestCase
{
    #[DataProvider('lockModes')]
    public function testManagedRecordsAcquireTheRequestedLockWithoutReloading(bool|null $write, string $lockClause): void
    {
        require 'config/doctrine-types.php';
        $accountId  = IdentityId::create();
        $identifier = $accountId->toString();
        $record     = IdentityAccount::create(Account::create($accountId, new AccountEmail('locks@example.com'), new DateTimeImmutable('2026-10-15T10:00:00Z')));
        $uuid       = Uuid::fromString($identifier);
        $connection = $this->createMock(Connection::class);
        $platform   = $connection->method('getDatabasePlatform');
        $platform->willReturn(new MySQLPlatform());
        $transaction = $connection->method('isTransactionActive');
        $transaction->willReturn(true);
        if ($write !== null) {
            $query = $connection->expects(self::once());
            $query->method('executeQuery');
            $query->with(self::stringContains($lockClause), [$uuid], [UuidBinaryType::NAME]);
            $result = $this->createStub(Result::class);
            $query->willReturn($result);
        }

        if ($write === null) {
            $query = $connection->expects(self::never());
            $query->method('executeQuery');
        }

        $configuration = ORMSetup::createAttributeMetadataConfiguration(['src/Backendbase/Domain/IdentityAndAccess/Adapters/Persistence/Doctrine/Entity'], true);
        $configuration->enableNativeLazyObjects(true);
        $session = new IdentitySession(new EntityManagerFactory($configuration));
        $session->open($connection);
        $manager = $session->manager();
        $unit    = $manager->getUnitOfWork();
        $unit->registerManaged($record, ['id' => $uuid], []);
        $store = new IdentityRecordStore($session);
        try {
            self::assertSame($record, $store->find(IdentityAccount::class, $identifier, $write));
        } finally {
            $session->discard();
        }
    }

    /** @return iterable<string, array{bool|null, string}> */
    public static function lockModes(): iterable
    {
        yield 'read lock' => [false, 'LOCK IN SHARE MODE'];
        yield 'write lock' => [true, 'FOR UPDATE'];
        yield 'reference lookup without lock' => [null, ''];
    }
}
