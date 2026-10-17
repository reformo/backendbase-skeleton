<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\StateField;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidBinaryType;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'identity_account', options: ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_tr_0900_ai_ci', 'engine' => 'InnoDB'])]
#[ORM\UniqueConstraint(name: 'identity_account_active_email_unq', columns: ['active_email'])]
#[ORM\UniqueConstraint(name: 'identity_account_active_mobile_unq', columns: ['active_mobile'])]
class IdentityAccount
{
    #[ORM\Id]
    #[ORM\Column(type: UuidBinaryType::NAME)]
    protected UuidInterface $id;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true, insertable: false, updatable: false, generated: 'ALWAYS', options: ['fixed' => true, 'charset' => 'ascii', 'collation' => 'ascii_bin'], columnDefinition: 'CHAR(36) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (BIN_TO_UUID(id, 0)) VIRTUAL')]
    protected string $uuid;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    protected int $version = 1;

    #[ORM\Column(name: 'normalized_email', type: Types::STRING, length: 254, options: ['charset' => 'ascii', 'collation' => 'ascii_bin'])]
    private string $email;

    #[ORM\Column(name: 'normalized_mobile', type: Types::STRING, length: 16, options: ['charset' => 'ascii', 'collation' => 'ascii_bin'])]
    private string $mobile;

    #[ORM\Column(name: 'first_name', type: Types::STRING, length: 160, nullable: true)]
    private string|null $firstName = null;

    #[ORM\Column(name: 'last_name', type: Types::STRING, length: 160, nullable: true)]
    private string|null $lastName = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private DateTimeImmutable|null $deletedAt = null;

    #[ORM\Column(name: 'active_email', type: Types::STRING, length: 254, nullable: true, insertable: false, updatable: false, generated: 'ALWAYS', options: ['charset' => 'ascii', 'collation' => 'ascii_bin'], columnDefinition: 'VARCHAR(254) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN normalized_email ELSE NULL END) VIRTUAL')]
    private string|null $activeEmail = null;

    #[ORM\Column(name: 'active_mobile', type: Types::STRING, length: 16, nullable: true, insertable: false, updatable: false, generated: 'ALWAYS', options: ['charset' => 'ascii', 'collation' => 'ascii_bin'], columnDefinition: 'VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN normalized_mobile ELSE NULL END) VIRTUAL')]
    private string|null $activeMobile = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $state;

    #[ORM\Column(name: 'password_hash', type: Types::STRING, length: 255)]
    private string $passwordHash = '';

    public static function create(Account $account): self
    {
        $record = new self();
        $record->assignId($account->id());
        $record->synchronize($account);

        return $record;
    }

    public function synchronize(Account $account): void
    {
        $state = $account->snapshot();
        $this->assertRevision($state['revision']);
        $this->email        = $state['email'];
        $this->mobile       = $state['mobile'];
        $this->firstName    = $state['firstName'] ?? null;
        $this->lastName     = $state['lastName'] ?? null;
        $this->createdAt    = new DateTimeImmutable($state['createdAt'], new DateTimeZone('UTC'));
        $this->deletedAt    = $state['deletedAt'] === null ? null : new DateTimeImmutable($state['deletedAt'], new DateTimeZone('UTC'));
        $this->activeEmail  = $state['deletedAt'] === null ? $state['email'] : null;
        $this->activeMobile = $state['deletedAt'] === null ? $state['mobile'] : null;
        $this->state        = ['ownershipVerified' => $state['ownershipVerified'], 'enabled' => $state['enabled'], 'authVersion' => $state['authVersion']];
    }

    public function toDomain(): Account
    {
        $currentDeletedAt = $this->deletedAt;
        $currentCreatedAt = $this->createdAt;

        if ($this->activeEmail !== ($this->deletedAt === null ? $this->email : null) || $this->activeMobile !== ($this->deletedAt === null ? $this->mobile : null)) {
            throw AuthenticationUnavailable::create('The persisted identity projection is invalid.');
        }

        return Account::reconstitute(IdentityId::fromString($this->id()), [
            'email' => $this->email,
            'mobile' => $this->mobile,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'ownershipVerified' => StateField::boolean($this->state, 'ownershipVerified'),
            'enabled' => StateField::boolean($this->state, 'enabled'),
            'authVersion' => StateField::integer($this->state, 'authVersion'),
            'deletedAt' => $currentDeletedAt?->format('Y-m-d H:i:s.u'),
            'revision' => $this->version,
            'createdAt' => $currentCreatedAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function replacePasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
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

    private function assignId(string $id): void
    {
        $this->id   = Uuid::fromString($id);
        $this->uuid = $id;
    }
}
