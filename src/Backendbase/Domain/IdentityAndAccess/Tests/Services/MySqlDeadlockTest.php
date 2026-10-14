<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Services;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineIdentityTransaction;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\IdentityAccount;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\IdentitySession;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\UnitOfWork\EntityManagerFactory;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\DeadlockException;
use PHPUnit\Framework\TestCase;
use Tests\Support\Services\IsolatedMySql;
use Tests\Support\Services\MySqlWorker;

use function count;
use function getenv;
use function spl_object_id;

final class MySqlDeadlockTest extends TestCase
{
    public function testNativeDeadlockRollsBackAndRetriesWithAFreshEntityManager(): void
    {
        if (getenv('BACKENDBASE_SERVICE_TESTS') !== '1') {
            self::markTestSkipped('An explicitly prepared MySQL 8.4 target is required.');
        }

        $fixture = IsolatedMySql::create();
        $worker  = null;
        try {
            $identities = self::accounts($fixture);
            $connection = $fixture->connection();
            $worker     = MySqlWorker::start($connection, 'deadlock', $identities);
            $workerId   = (int) $worker->receive();
            self::assertSame('locked', $worker->receive());
            $manager = $fixture->manager();
            $session = new IdentitySession(new EntityManagerFactory($manager->getConfiguration()));
            $unit    = new DoctrineIdentityTransaction($connection, $session);
            $opened  = [];
            $errors  = [];
            $unit->run(static function () use ($connection, $worker, $workerId, $identities, $session, &$opened, &$errors): void {
                $opened[] = $session->manager();
                $connection->executeStatement('UPDATE identity_account SET version = version + 1 WHERE id = UUID_TO_BIN(?, 0)', [$identities[0]]);
                if (count($opened) === 1) {
                    $worker->send('continue');
                    $worker->awaitLockWait($connection, $workerId);
                }

                try {
                    $connection->fetchOne('SELECT id FROM identity_account WHERE id = UUID_TO_BIN(?, 0) FOR UPDATE', [$identities[1]]);
                } catch (DeadlockException $exception) {
                    $cause    = $exception->getPrevious();
                    $errors[] = $cause?->getCode();

                    throw $exception;
                }
            });
            self::assertSame('committed', $worker->receive());
            self::assertSame([1213], $errors);
            self::assertCount(2, $opened);
            self::assertNotSame(spl_object_id($opened[0]), spl_object_id($opened[1]));
            self::assertFalse($opened[0]->isOpen());
            self::assertFalse($opened[1]->isOpen());
            self::assertFalse($connection->isTransactionActive());
            self::assertSame(3, $connection->fetchOne('SELECT version FROM identity_account WHERE id = UUID_TO_BIN(?, 0)', [$identities[0]]));
            self::assertSame(2, $connection->fetchOne('SELECT version FROM identity_account WHERE id = UUID_TO_BIN(?, 0)', [$identities[1]]));
        } finally {
            $worker?->close();
            $fixture->destroy();
        }
    }

    /** @return list<string> */
    private static function accounts(IsolatedMySql $fixture): array
    {
        $manager    = $fixture->manager();
        $identities = [];
        for ($index = 0; $index < 9; $index++) {
            $account      = Account::create(IdentityId::create(), new AccountEmail('deadlock-' . $index . '@example.com'), new DateTimeImmutable('2026-10-14T10:00:00Z'));
            $identities[] = $account->id();
            $manager->persist(IdentityAccount::create($account));
        }

        $manager->flush();

        return $identities;
    }
}
