<?php

declare(strict_types=1);

namespace Tests\Support\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;

final class EntityManagerFactory
{
    public static function create(Connection $connection): EntityManagerInterface
    {
        $configuration = ORMSetup::createAttributeMetadataConfiguration([], true);
        $configuration->enableNativeLazyObjects(true);

        return new EntityManager($connection, $configuration);
    }
}
