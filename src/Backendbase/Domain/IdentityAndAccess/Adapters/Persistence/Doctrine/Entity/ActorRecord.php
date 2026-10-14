<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\StateField;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Actor;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidBinaryType;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\MappedSuperclass]
abstract class ActorRecord extends IdentifiedRecord
{
    #[ORM\Column(name: 'account_id', type: UuidBinaryType::NAME)]
    private UuidInterface $accountId;

    #[ORM\Column(name: 'normalized_login_name', type: Types::STRING, length: 64, options: ['charset' => 'ascii', 'collation' => 'ascii_bin'])]
    private string $loginName;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private DateTimeImmutable|null $deletedAt = null;

    #[ORM\Column(name: 'active_login_name', type: Types::STRING, length: 64, nullable: true, insertable: false, updatable: false, generated: 'ALWAYS', options: ['charset' => 'ascii', 'collation' => 'ascii_bin'], columnDefinition: 'VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN normalized_login_name ELSE NULL END) VIRTUAL')]
    private string|null $activeLoginName = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $state;

    public function synchronize(Actor $actor): void
    {
        $currentAccountId = $this->accountId ?? null;

        $state = $actor->snapshot();
        $this->assertRevision($state['revision']);
        if ($currentAccountId !== null && $currentAccountId->toString() !== $state['accountId']) {
            throw IdentityConflict::create('The actor Account reference cannot change.');
        }

        $this->accountId       = Uuid::fromString($state['accountId']);
        $this->loginName       = $state['loginName'];
        $this->createdAt       = new DateTimeImmutable($state['createdAt'], new DateTimeZone('UTC'));
        $this->deletedAt       = $state['deletedAt'] === null ? null : new DateTimeImmutable($state['deletedAt'], new DateTimeZone('UTC'));
        $this->activeLoginName = $state['deletedAt'] === null ? $state['loginName'] : null;
        $this->state           = ['approved' => $state['approved'], 'invitation' => $state['invitation'], 'approval' => $state['approval']];
    }

    /** @return array{accountId: string, loginName: string, approved: bool, invitation: string|null, approval: string|null, deletedAt: string|null, revision: int, createdAt: string} */
    protected function snapshot(): array
    {
        $currentDeletedAt = $this->deletedAt;
        $currentCreatedAt = $this->createdAt;

        if ($this->activeLoginName !== ($this->deletedAt === null ? $this->loginName : null)) {
            throw AuthenticationUnavailable::create('The persisted identity projection is invalid.');
        }

        $accountId = $this->accountId;

        return [
            'accountId' => $accountId->toString(),
            'loginName' => $this->loginName,
            'approved' => StateField::boolean($this->state, 'approved'),
            'invitation' => StateField::nullableString($this->state, 'invitation'),
            'approval' => StateField::nullableString($this->state, 'approval'),
            'deletedAt' => $currentDeletedAt?->format('Y-m-d H:i:s.u'),
            'revision' => $this->version,
            'createdAt' => $currentCreatedAt->format('Y-m-d H:i:s.u'),
        ];
    }

    abstract public function toDomain(): Actor;
}
