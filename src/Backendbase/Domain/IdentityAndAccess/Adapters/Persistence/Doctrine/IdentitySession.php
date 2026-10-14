<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\UnitOfWork\EntityManagerFactory;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

final class IdentitySession
{
    private EntityManagerInterface|null $manager = null;

    public function __construct(private readonly EntityManagerFactory $factory)
    {
    }

    public function open(Connection $connection): void
    {
        $factory       = $this->factory;
        $this->manager = $factory->create($connection);
    }

    public function manager(): EntityManagerInterface
    {
        if ($this->manager === null) {
            throw new LogicException('An identity transaction is required.');
        }

        return $this->manager;
    }

    public function discard(): void
    {
        $manager       = $this->manager;
        $this->manager = null;
        $manager?->close();
    }
}
