<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Doctrine\Entity;

use Backendbase\Domain\ExampleCatalog\Domain\Entry as DomainEntry;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineEntity;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineEntityMethods;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Doctrine\ORM\Mapping\Version;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryType;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[Entity]
#[Table(name: 'example_table', options: ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_tr_0900_ai_ci', 'engine' => 'InnoDB'])]
#[Index(name: 'example_search_idx', columns: ['type', 'type_target_id', 'lookup_group', 'lookup_key', 'is_active', 'deleted_at'])]
#[Index(name: 'example_relation_idx', columns: ['type', 'type_target_id', 'lookup_key', 'deleted_at'])]
#[UniqueConstraint(
    name: 'example_active_data_unq',
    columns: ['type', 'normalized_type_target_id', 'lookup_group', 'lookup_key', 'active_uniqueness_key'],
)]
class ExampleTable implements DoctrineEntity
{
    use DoctrineEntityMethods;

    #[Id]
    #[Column(type: UuidBinaryType::NAME)]
    protected UuidInterface $id;

    #[Column(type: Types::STRING, length: 36, nullable: true, insertable: false, updatable: false, generated: 'ALWAYS', options: ['fixed' => true, 'charset' => 'ascii', 'collation' => 'ascii_bin'], columnDefinition: 'CHAR(36) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (BIN_TO_UUID(id, 0)) VIRTUAL')]
    private string $uuid;

    #[Version]
    #[Column(type: Types::INTEGER)]
    private int $version = 1;

    #[Column(type: Types::ENUM, length: 32, enumType: EntryType::class)]
    private EntryType $type;

    #[Column(name: 'type_target_id', type: Types::BIGINT, nullable: true, options: ['unsigned' => true])]
    private int|null $typeTargetId = null;

    #[Column(
        name: 'normalized_type_target_id',
        type: Types::BIGINT,
        nullable: true,
        insertable: false,
        updatable: false,
        options: ['unsigned' => true],
        columnDefinition: 'BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(type_target_id, 0)) STORED',
        generated: 'ALWAYS',
    )]
    private int $normalizedTypeTargetId = 0;

    #[Column(name: 'lookup_group', type: Types::STRING, length: 32)]
    private string $group;

    #[Column(name: 'lookup_key', type: Types::STRING, length: 160)]
    private string $lookupKey;

    #[Column(name: 'lookup_value', type: Types::STRING, length: 2048)]
    private string $lookupValue;

    /** @var array<string, mixed> */
    #[Column(type: Types::JSON)]
    private array $details;

    #[Column(name: 'is_active', type: Types::INTEGER, length: 1, options: ['unsigned' => true, 'default' => 1])]
    private int $isActive;

    #[Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private DateTimeImmutable|null $updatedAt = null;

    #[Column(name: 'deleted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private DateTimeImmutable|null $deletedAt = null;

    #[Column(
        name: 'active_uniqueness_key',
        type: Types::BOOLEAN,
        nullable: true,
        insertable: false,
        updatable: false,
        options: ['unsigned' => true],
        columnDefinition: 'TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END) STORED',
        generated: 'ALWAYS',
    )]
    private bool|null $activeUniquenessKey = true;

    #[Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public static function fromDomain(DomainEntry $entry): self
    {
        $record = new self();
        $record->synchronize($entry);

        return $record;
    }

    #[Override]
    public function id(): string
    {
        $id = $this->id;

        return $id->toString();
    }

    public function synchronize(DomainEntry $entry): void
    {
        $state                        = $entry->snapshot();
        $this->id                     = Uuid::fromString($state->uuid());
        $this->uuid                   = $state->uuid();
        $this->type                   = $state->type();
        $this->typeTargetId           = $state->typeTargetId();
        $this->normalizedTypeTargetId = $state->typeTargetId() ?? 0;
        $this->group                  = $state->group();
        $this->lookupKey              = $state->lookupKey();
        $this->lookupValue            = $state->lookupValue();
        $this->details                = $state->details();
        $this->isActive               = (int) $state->isActive();
        $this->createdAt              = $state->createdAt();
        $this->updatedAt              = $state->updatedAt();
        $this->deletedAt              = $state->removedAt();
        $this->activeUniquenessKey    = $state->removedAt() === null ? true : null;
    }

    public function toDomain(): DomainEntry
    {
        return DomainEntry::reconstitute(
            $this->uuid,
            $this->type,
            $this->typeTargetId,
            $this->group,
            $this->lookupKey,
            $this->lookupValue,
            $this->details,
            (bool) $this->isActive,
            $this->createdAt,
            $this->updatedAt ?? $this->createdAt,
            $this->deletedAt,
        );
    }
}
