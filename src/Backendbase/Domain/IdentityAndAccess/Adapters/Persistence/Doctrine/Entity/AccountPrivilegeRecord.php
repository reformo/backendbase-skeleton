<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity;

use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineEntity;
use Backendbase\Infrastructure\Adapters\Persistence\Doctrine\DoctrineEntityMethods;
use Backendbase\Shared\Helpers\DateTimeImmutable as DateTimeImmutableFactory;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Override;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\Uuid;

#[Entity]
#[Table(name: 'example_account_privileged')]
#[Index(name: 'example_account_privileged_active_idx', columns: ['account_id', 'expired_at'])]
#[Index(name: 'example_account_privileged_privilege_idx', columns: ['privilege_id'])]
#[UniqueConstraint(name: 'example_account_privileged_uuid_unq', columns: ['uuid'])]
#[UniqueConstraint(name: 'example_account_privilege_unq', columns: ['account_id', 'privilege_id'])]
class AccountPrivilegeRecord implements DoctrineEntity
{
    use DoctrineEntityMethods;

    #[Id]
    #[Column(type: Types::BIGINT, options: ['unsigned' => true])]
    #[GeneratedValue]
    protected int $id;

    #[Column(type: UuidType::NAME)]
    private string $uuid;

    #[ManyToOne(targetEntity: AccountRecord::class, inversedBy: 'grants')]
    #[JoinColumn(name: 'account_id', nullable: false)]
    private AccountRecord $account;

    #[ManyToOne(targetEntity: PrivilegeRecord::class)]
    #[JoinColumn(name: 'privilege_id', nullable: false)]
    private PrivilegeRecord $privilege;

    #[Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[Column(name: 'expired_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private DateTimeImmutable|null $expiredAt = null;

    public function __construct(AccountRecord $account, PrivilegeRecord $privilege)
    {
        $this->uuid      = Uuid::uuid7()->toString();
        $this->account   = $account;
        $this->privilege = $privilege;
        $this->createdAt = DateTimeImmutableFactory::create();
    }

    #[Override]
    public function id(): int
    {
        return $this->id;
    }

    public function privilege(): PrivilegeRecord
    {
        return $this->privilege;
    }

    public function isActive(): bool
    {
        return $this->expiredAt === null;
    }

    public function expire(DateTimeImmutable $expiredAt): void
    {
        $this->expiredAt = $expiredAt;
    }

    public function reactivate(): void
    {
        $this->expiredAt = null;
    }
}
