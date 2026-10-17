<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Operator;

use Backendbase\Domain\IdentityAndAccess\Contracts\OperatorStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final readonly class DoctrineOperatorStore implements OperatorStore
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param callable(): Result $work
     *
     * @return Result
     *
     * @template Result
     */
    public function exclusive(callable $work): mixed
    {
        $connection = $this->connection;
        if (! $connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return $work();
        }

        if ((int) $connection->fetchOne("SELECT GET_LOCK(SHA2(CONCAT(DATABASE(), ':first-admin'), 256), 5)") !== 1) {
            throw IdentityConflict::create('First-Admin setup is busy. Try again after the current operation completes.');
        }

        try {
            return $work();
        } finally {
            $this->release();
        }
    }

    public function assertFirstAdminAvailable(): void
    {
        $connection = $this->connection;
        if ($connection->fetchOne('SELECT id FROM `admin` LIMIT 1') !== false) {
            throw IdentityConflict::create('First-Admin setup is available only before any Admin record exists.');
        }
    }

    public function assertBootstrapActor(string $actorId): void
    {
        $connection = $this->connection;
        $id         = Uuid::fromString($actorId);
        $identifier = $id->getBytes();
        if ($connection->fetchFirstColumn('SELECT id FROM `admin` ORDER BY id LIMIT 2') !== [$identifier]) {
            throw IdentityConflict::create('Operator approval requires the only Admin record.');
        }
    }

    private function release(): void
    {
        $connection = $this->connection;
        if ((int) $connection->fetchOne("SELECT RELEASE_LOCK(SHA2(CONCAT(DATABASE(), ':first-admin'), 256))") !== 1) {
            throw new RuntimeException('The first-Admin setup lock could not be released.');
        }
    }
}
