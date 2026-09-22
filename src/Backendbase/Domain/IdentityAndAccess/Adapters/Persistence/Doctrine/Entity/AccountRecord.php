<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity;

use Backendbase\Domain\IdentityAndAccess\Domain\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountId;
use Backendbase\Domain\IdentityAndAccess\Domain\AccountPrivileges;
use Backendbase\Shared\Helpers\DateTimeImmutable as DateTimeImmutableFactory;
use Backendbase\Shared\Persistence\Doctrine\DoctrineEntity;
use Backendbase\Shared\Persistence\DoctrineEntityMethods;
use Backendbase\Shared\Primitives\Email;
use Backendbase\Shared\Primitives\PasswordHash;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Override;
use Ramsey\Uuid\Doctrine\UuidType;

#[Entity]
#[Table(name: 'example_accounts')]
#[UniqueConstraint(name: 'example_accounts_uuid_unq', columns: ['uuid'])]
#[UniqueConstraint(name: 'example_accounts_active_email_unq', columns: ['email', 'active_uniqueness_key'])]
class AccountRecord implements DoctrineEntity
{
    use DoctrineEntityMethods;

    #[Id]
    #[Column(type: Types::BIGINT, options: ['unsigned' => true])]
    #[GeneratedValue]
    protected int $id;

    #[Column(type: UuidType::NAME)]
    private string $uuid;

    #[Column(type: Types::STRING, length: 254)]
    private string $email;

    #[Column(name: 'password_hash', type: Types::STRING, length: 255)]
    private string $passwordHash;

    #[Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[Column(name: 'deleted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private DateTimeImmutable|null $deletedAt = null;

    #[Column(
        name: 'active_uniqueness_key',
        type: Types::SMALLINT,
        nullable: true,
        insertable: false,
        updatable: false,
        options: ['unsigned' => true],
        columnDefinition: 'TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END) STORED',
        generated: 'ALWAYS',
    )]
    private int|null $activeUniquenessKey = 1;

    /** @var Collection<int, AccountPrivilegeRecord> */
    #[OneToMany(targetEntity: AccountPrivilegeRecord::class, mappedBy: 'account', cascade: ['persist', 'refresh'])]
    private Collection $grants;

    private function __construct(Account $account)
    {
        $this->createdAt = DateTimeImmutableFactory::create();
        $this->grants    = new ArrayCollection();
        $this->synchronize($account);
    }

    public static function fromDomain(Account $account): self
    {
        return new self($account);
    }

    #[Override]
    public function id(): int
    {
        return $this->id;
    }

    public function synchronize(Account $account): void
    {
        $this->uuid                = $account->id()->toString();
        $this->email               = $account->email()->toString();
        $this->passwordHash        = $account->passwordHash()->toString();
        $this->deletedAt           = $account->retiredAt();
        $this->activeUniquenessKey = $account->isRetired() ? null : 1;
    }

    /** @param list<PrivilegeRecord> $privileges */
    public function replacePrivileges(array $privileges): void
    {
        $replacementSlugs = [];
        foreach ($privileges as $privilege) {
            $replacementSlugs[$privilege->slug()] = $privilege;
        }

        foreach ($this->grants as $grant) {
            $slug = $grant->privilege()->slug();
            if (isset($replacementSlugs[$slug])) {
                $grant->reactivate();
                unset($replacementSlugs[$slug]);
                continue;
            }

            if (! $grant->isActive()) {
                continue;
            }

            $grant->expire(DateTimeImmutableFactory::create());
        }

        foreach ($replacementSlugs as $privilege) {
            $this->grants->add(new AccountPrivilegeRecord($this, $privilege));
        }
    }

    public function toDomain(): Account
    {
        $privilegeSlugs = [];
        foreach ($this->grants as $grant) {
            if (! $grant->isActive()) {
                continue;
            }

            $privilegeSlugs[] = $grant->privilege()->slug();
        }

        return Account::reconstitute(
            AccountId::fromString($this->uuid),
            new Email($this->email),
            PasswordHash::create($this->passwordHash),
            new AccountPrivileges($privilegeSlugs),
            $this->deletedAt,
        );
    }
}
