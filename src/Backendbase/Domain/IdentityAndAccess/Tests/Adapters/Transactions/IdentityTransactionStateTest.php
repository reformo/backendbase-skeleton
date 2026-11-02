<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Transactions;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineIdentityTransaction;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\IdentitySession;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\UnitOfWork\EntityManagerFactory;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\ORMSetup;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class IdentityTransactionStateTest extends TestCase
{
    public function testNonRetryableFailureRollsBackOnceAndDiscardsTheManager(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE transaction_probe (attempt INTEGER NOT NULL)');
        $configuration = ORMSetup::createAttributeMetadataConfiguration(['src/Backendbase/Domain/IdentityAndAccess/Adapters/Persistence/Doctrine/Entity'], true);
        $configuration->enableNativeLazyObjects(true);
        $session  = new IdentitySession(new EntityManagerFactory($configuration));
        $unit     = new DoctrineIdentityTransaction($connection, $session);
        $failure  = new RuntimeException('Non-retryable operation failure.');
        $attempts = 0;
        $opened   = null;
        $caught   = null;
        try {
            $unit->run(static function () use ($connection, $session, $failure, &$attempts, &$opened): void {
                $opened = $session->manager();
                $attempts++;
                $connection->insert('transaction_probe', ['attempt' => $attempts]);

                throw $failure;
            });
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }

        self::assertSame($failure, $caught);
        self::assertSame(1, $attempts);
        self::assertSame([], $connection->fetchFirstColumn('SELECT attempt FROM transaction_probe'));
        self::assertFalse($connection->isTransactionActive());
        self::assertNotNull($opened);
        self::assertFalse($opened->isOpen());
        $this->expectException(LogicException::class);
        $session->manager();
    }
}
