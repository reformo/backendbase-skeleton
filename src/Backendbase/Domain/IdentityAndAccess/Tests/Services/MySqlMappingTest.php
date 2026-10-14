<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Services;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\IdentityAccount;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\Operations\MySqlDiagnostics;
use DateTimeImmutable;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Tests\Support\Services\IsolatedMySql;

use function getenv;

final class MySqlMappingTest extends TestCase
{
    public function testAssignedBinaryUuidAndVirtualProjectionHaveNoSchemaDrift(): void
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            self::markTestSkipped('An explicitly prepared MySQL 8.4 target is required.');
        }

        $fixture = IsolatedMySql::create();
        try {
            $manager = $fixture->manager();
            $id      = IdentityId::create();
            $account = Account::create($id, new AccountEmail('test@example.com'), new DateTimeImmutable('2026-10-14T10:00:00.123456Z'));
            $manager->persist(IdentityAccount::create($account));
            $manager->flush();
            $manager->clear();
            $record = $manager->find(IdentityAccount::class, $id->toString());
            self::assertInstanceOf(IdentityAccount::class, $record);
            self::assertSame($id->toString(), $record->id());
            $loaded = $record->toDomain();
            self::assertSame('2026-10-14 10:00:00.123456', $loaded->snapshot()['createdAt']);
            $connection = $fixture->connection();
            self::assertSame($id->toString(), $connection->fetchOne('SELECT uuid FROM identity_account'));
            self::assertSame(16, $connection->fetchOne('SELECT OCTET_LENGTH(id) FROM identity_account'));
            $metadataFactory = $manager->getMetadataFactory();
            self::assertSame([], new SchemaTool($manager)->getUpdateSchemaSql($metadataFactory->getAllMetadata()));
            self::assertSame(0, $connection->fetchOne("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY'"));
            self::assertSame(['utf8mb4_tr_0900_ai_ci'], $connection->fetchFirstColumn('SELECT DISTINCT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'));
            $diagnostics = new MySqlDiagnostics($connection)->inspect();
            self::assertSame(0, $diagnostics['foreignKeys']);
            self::assertArrayHasKey('lock_deadlocks', $diagnostics);
            self::assertArrayHasKey('buffer_pool_reads', $diagnostics);
        } finally {
            $fixture->destroy();
        }
    }
}
