<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity;

use Backendbase\Shared\Persistence\Doctrine\DoctrineEntity;
use Backendbase\Shared\Persistence\DoctrineEntityMethods;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Override;
use Ramsey\Uuid\Doctrine\UuidType;

#[Entity]
#[Table(name: 'example_privileges')]
#[UniqueConstraint(name: 'example_privileges_uuid_unq', columns: ['uuid'])]
#[UniqueConstraint(name: 'example_privileges_slug_unq', columns: ['slug'])]
class PrivilegeRecord implements DoctrineEntity
{
    use DoctrineEntityMethods;

    #[Id]
    #[Column(type: Types::BIGINT, options: ['unsigned' => true])]
    #[GeneratedValue]
    protected int $id;

    #[Column(type: UuidType::NAME)]
    protected string $uuid;

    #[Column(type: Types::STRING, length: 160)]
    protected string $title;

    #[Column(type: Types::STRING, length: 100)]
    protected string $slug;

    #[Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $createdAt;

    #[Column(name: 'deleted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected DateTimeImmutable|null $deletedAt = null;

    #[Override]
    public function id(): int
    {
        return $this->id;
    }

    public function slug(): string
    {
        return $this->slug;
    }
}
