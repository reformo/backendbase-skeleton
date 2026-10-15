<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\StateField;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Actor;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\User;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidBinaryType;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'identity_user', options: ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_tr_0900_ai_ci', 'engine' => 'InnoDB'])]
#[ORM\UniqueConstraint(name: 'identity_user_account_unq', columns: ['account_id'])]
class IdentityUser
{
    #[ORM\Id]
    #[ORM\Column(type: UuidBinaryType::NAME)]
    protected UuidInterface $id;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true, insertable: false, updatable: false, generated: 'ALWAYS', options: ['fixed' => true, 'charset' => 'ascii', 'collation' => 'ascii_bin'], columnDefinition: 'CHAR(36) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (BIN_TO_UUID(id, 0)) VIRTUAL')]
    protected string $uuid;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    protected int $version = 1;

    #[ORM\Column(name: 'account_id', type: UuidBinaryType::NAME)]
    private UuidInterface $accountId;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private DateTimeImmutable|null $deletedAt = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $state;

    public static function create(User $actor): self
    {
        $record = new self();
        $record->assignId($actor->id());
        $record->synchronize($actor);

        return $record;
    }

    public function synchronize(Actor $actor): void
    {
        $currentAccountId = $this->accountId ?? null;

        $state = $actor->snapshot();
        $this->assertRevision($state['revision']);
        if ($currentAccountId !== null && $currentAccountId->toString() !== $state['accountId']) {
            throw IdentityConflict::create('The actor Account reference cannot change.');
        }

        $this->accountId = Uuid::fromString($state['accountId']);
        $this->createdAt = new DateTimeImmutable($state['createdAt'], new DateTimeZone('UTC'));
        $this->deletedAt = $state['deletedAt'] === null ? null : new DateTimeImmutable($state['deletedAt'], new DateTimeZone('UTC'));
        $this->state     = ['approved' => $state['approved'], 'invitation' => $state['invitation'], 'approval' => $state['approval']];
    }

    public function toDomain(): User
    {
        return new User(IdentityId::fromString($this->id()), $this->snapshot());
    }

    public function id(): string
    {
        $id = $this->id;

        return $id->toString();
    }

    public function assertRevision(int $expected): void
    {
        if ($expected !== $this->version) {
            throw IdentityConflict::create('The identity record changed. Reload the record before another attempt.');
        }
    }

    /** @return array{accountId: string, approved: bool, invitation: string|null, approval: string|null, deletedAt: string|null, revision: int, createdAt: string} */
    private function snapshot(): array
    {
        $currentDeletedAt = $this->deletedAt;
        $currentCreatedAt = $this->createdAt;

        $accountId = $this->accountId;

        return [
            'accountId' => $accountId->toString(),
            'approved' => StateField::boolean($this->state, 'approved'),
            'invitation' => StateField::nullableString($this->state, 'invitation'),
            'approval' => StateField::nullableString($this->state, 'approval'),
            'deletedAt' => $currentDeletedAt?->format('Y-m-d H:i:s.u'),
            'revision' => $this->version,
            'createdAt' => $currentCreatedAt->format('Y-m-d H:i:s.u'),
        ];
    }

    private function assignId(string $id): void
    {
        $this->id   = Uuid::fromString($id);
        $this->uuid = $id;
    }
}
