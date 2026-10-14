<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineIdentityTransaction;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\IdentityAccount;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\IdentitySession;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\UnitOfWork\EntityManagerFactory;
use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMSetup;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_unique;
use function spl_object_id;

final class IdentityTransactionTest extends TestCase
{
    #[DataProvider('retryOutcomes')]
    public function testRetryableFailuresRollBackAndUseAtMostThreeFreshManagers(bool $exhausted): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE transaction_probe (attempt INTEGER NOT NULL)');
        $session  = self::session();
        $unit     = new DoctrineIdentityTransaction($connection, $session);
        $failure  = new LockWaitTimeoutException(new class ('Lock timed out.') extends AbstractException {
        }, null);
        $opened   = [];
        $attempts = 0;
        try {
            $result = $unit->run(static function () use ($connection, $session, $failure, $exhausted, &$opened, &$attempts): string {
                $opened[] = $session->manager();
                $attempts++;
                $connection->insert('transaction_probe', ['attempt' => $attempts]);
                if ($exhausted || $attempts < 3) {
                    throw $failure;
                }

                return 'committed';
            });
            self::assertFalse($exhausted);
            self::assertSame('committed', $result);
        } catch (LockWaitTimeoutException $exception) {
            self::assertTrue($exhausted);
            self::assertSame($failure, $exception);
        }

        self::assertSame(3, $attempts);
        self::assertCount(3, array_unique(array_map(spl_object_id(...), $opened)));
        self::assertSame($exhausted ? [] : [3], $connection->fetchFirstColumn('SELECT attempt FROM transaction_probe'));
        self::assertFalse($connection->isTransactionActive());
        foreach ($opened as $manager) {
            self::assertFalse($manager->isOpen());
        }

        $this->expectException(LogicException::class);
        $session->manager();
    }

    /** @return iterable<string, array{bool}> */
    public static function retryOutcomes(): iterable
    {
        yield 'third attempt commits' => [false];
        yield 'third failure stops retrying' => [true];
    }

    public function testOptimisticConflictsRollBackWithoutRetryAndPreserveTheirCause(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE transaction_probe (attempt INTEGER NOT NULL)');
        $session  = self::session();
        $unit     = new DoctrineIdentityTransaction($connection, $session);
        $failure  = OptimisticLockException::lockFailed(IdentityAccount::class);
        $attempts = 0;
        try {
            $unit->run(static function () use ($connection, $failure, &$attempts): void {
                $attempts++;
                $connection->insert('transaction_probe', ['attempt' => $attempts]);

                throw $failure;
            });
        } catch (IdentityConflict $exception) {
            self::assertSame($failure, $exception->getPrevious());
        }

        self::assertSame(1, $attempts);
        self::assertSame([], $connection->fetchFirstColumn('SELECT attempt FROM transaction_probe'));
        self::assertFalse($connection->isTransactionActive());
        $this->expectException(LogicException::class);
        $session->manager();
    }

    private static function session(): IdentitySession
    {
        $configuration = ORMSetup::createAttributeMetadataConfiguration(['src/Backendbase/Domain/IdentityAndAccess/Adapters/Persistence/Doctrine/Entity'], true);
        $configuration->enableNativeLazyObjects(true);

        return new IdentitySession(new EntityManagerFactory($configuration));
    }
}
