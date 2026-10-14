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

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private DateTimeImmutable|null $deletedAt = null;

    #[ORM\Column(name: 'active_email', type: Types::STRING, length: 254, nullable: true, insertable: false, updatable: false, generated: 'ALWAYS', options: ['charset' => 'ascii', 'collation' => 'ascii_bin'], columnDefinition: 'VARCHAR(254) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN normalized_email ELSE NULL END) VIRTUAL')]
    private string|null $activeEmail = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $state;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $credentials = ['passwordHash' => '', 'totpSecret' => null, 'pendingTotpSecret' => null, 'lastTotpStep' => -1, 'failedMfaAttempts' => 0, 'blockedUntil' => null];

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
        $this->email       = $state['email'];
        $this->createdAt   = new DateTimeImmutable($state['createdAt'], new DateTimeZone('UTC'));
        $this->deletedAt   = $state['deletedAt'] === null ? null : new DateTimeImmutable($state['deletedAt'], new DateTimeZone('UTC'));
        $this->activeEmail = $state['deletedAt'] === null ? $state['email'] : null;
        $this->state       = ['owned' => $state['owned'], 'enabled' => $state['enabled'], 'authVersion' => $state['authVersion']];
    }

    public function toDomain(): Account
    {
        $currentDeletedAt = $this->deletedAt;
        $currentCreatedAt = $this->createdAt;

        if ($this->activeEmail !== ($this->deletedAt === null ? $this->email : null)) {
            throw AuthenticationUnavailable::create('The persisted identity projection is invalid.');
        }

        return Account::reconstitute(IdentityId::fromString($this->id()), [
            'email' => $this->email,
            'owned' => StateField::boolean($this->state, 'owned'),
            'enabled' => StateField::boolean($this->state, 'enabled'),
            'authVersion' => StateField::integer($this->state, 'authVersion'),
            'deletedAt' => $currentDeletedAt?->format('Y-m-d H:i:s.u'),
            'revision' => $this->version,
            'createdAt' => $currentCreatedAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    /** @return array{passwordHash: string, totpSecret: string|null, pendingTotpSecret: string|null, lastTotpStep: int, failedMfaAttempts: int, blockedUntil: string|null} */
    public function credentials(): array
    {
        return [
            'passwordHash' => StateField::string($this->credentials, 'passwordHash'),
            'totpSecret' => StateField::nullableString($this->credentials, 'totpSecret'),
            'pendingTotpSecret' => StateField::nullableString($this->credentials, 'pendingTotpSecret'),
            'lastTotpStep' => StateField::integer($this->credentials, 'lastTotpStep'),
            'failedMfaAttempts' => StateField::integer($this->credentials, 'failedMfaAttempts'),
            'blockedUntil' => StateField::nullableString($this->credentials, 'blockedUntil'),
        ];
    }

    /** @param array{passwordHash: string, totpSecret: string|null, pendingTotpSecret: string|null, lastTotpStep: int, failedMfaAttempts: int, blockedUntil: string|null} $credentials */
    public function replaceCredentials(array $credentials): void
    {
        $this->credentials = $credentials;
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
