<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Persistence\Doctrine\UnitOfWork;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;

final readonly class EntityManagerFactory
{
    public function __construct(private Configuration $configuration)
    {
    }

    public function create(Connection $connection): EntityManagerInterface
    {
        $platform = $connection->getDatabasePlatform();
        $platform->registerDoctrineTypeMapping('datetime', Types::DATETIME_IMMUTABLE);

        return new EntityManager($connection, $this->configuration);
    }
}
