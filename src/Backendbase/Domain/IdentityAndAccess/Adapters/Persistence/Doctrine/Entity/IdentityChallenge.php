<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\StateField;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Challenges\Challenge;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidBinaryType;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'identity_challenge', options: ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_tr_0900_ai_ci', 'engine' => 'InnoDB'])]
#[ORM\Index(name: 'identity_challenge_account_idx', columns: ['account_id', 'expires_at'])]
class IdentityChallenge
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

    #[ORM\Column(name: 'actor_id', type: UuidBinaryType::NAME)]
    private UuidInterface $actorId;

    #[ORM\Column(type: Types::STRING, length: 16, options: ['charset' => 'ascii', 'collation' => 'ascii_bin'])]
    private string $realm;

    #[ORM\Column(type: Types::STRING, length: 32, options: ['charset' => 'ascii', 'collation' => 'ascii_bin'])]
    private string $purpose;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $expiresAt;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $state;

    public static function create(Challenge $challenge): self
    {
        $record = new self();
        $record->assignId($challenge->id());
        $record->synchronize($challenge);

        return $record;
    }

    public function synchronize(Challenge $challenge): void
    {
        $state = $challenge->snapshot();
        $this->assertRevision($state['revision']);
        $this->accountId = Uuid::fromString($state['accountId']);
        $this->actorId   = Uuid::fromString($state['actorId']);
        $this->realm     = $state['realm'];
        $this->purpose   = $state['purpose'];
        $this->expiresAt = new DateTimeImmutable($state['expiresAt'], new DateTimeZone('UTC'));
        $this->state     = ['proofHash' => $state['proofHash'], 'attempts' => $state['attempts'], 'consumed' => $state['consumed'], 'authVersion' => $state['authVersion']];
    }

    public function toDomain(): Challenge
    {
        $currentExpiresAt = $this->expiresAt;

        $accountId = $this->accountId;
        $actorId   = $this->actorId;

        return new Challenge(IdentityId::fromString($this->id()), [
            'accountId' => $accountId->toString(),
            'actorId' => $actorId->toString(),
            'realm' => $this->realm,
            'purpose' => $this->purpose,
            'expiresAt' => $currentExpiresAt->format('Y-m-d H:i:s.u'),
            'proofHash' => StateField::string($this->state, 'proofHash'),
            'attempts' => StateField::integer($this->state, 'attempts'),
            'consumed' => StateField::boolean($this->state, 'consumed'),
            'authVersion' => StateField::integer($this->state, 'authVersion'),
            'revision' => $this->version,
        ]);
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
