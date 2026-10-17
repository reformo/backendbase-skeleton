<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo as Contact;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\ContactState;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidBinaryType;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'contact_info', options: ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_tr_0900_ai_ci', 'engine' => 'InnoDB'])]
#[ORM\UniqueConstraint(name: 'contact_info_context_slot_unq', columns: ['context_hash', 'realm', 'purpose', 'contact_type'])]
class ContactInfo
{
    #[ORM\Id]
    #[ORM\Column(type: UuidBinaryType::NAME)]
    private UuidInterface $id;
    #[ORM\Column(type: Types::STRING, length: 36, nullable: true, insertable: false, updatable: false, generated: 'ALWAYS', options: ['fixed' => true, 'charset' => 'ascii', 'collation' => 'ascii_bin'], columnDefinition: 'CHAR(36) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (BIN_TO_UUID(id, 0)) VIRTUAL')]
    protected string $uuid;
    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;
    #[ORM\Column(name: 'context_hash', length: 64, options: ['charset' => 'ascii', 'collation' => 'ascii_bin'])]
    private string $contextHash;
    #[ORM\Column(length: 16, options: ['charset' => 'ascii', 'collation' => 'ascii_bin'])]
    private string $realm;
    #[ORM\Column(length: 40, options: ['charset' => 'ascii', 'collation' => 'ascii_bin'])]
    private string $purpose;
    #[ORM\Column(name: 'contact_type', length: 8, options: ['charset' => 'ascii', 'collation' => 'ascii_bin'])]
    private string $contactType;
    #[ORM\Column(name: 'normalized_value', length: 254, options: ['charset' => 'ascii', 'collation' => 'ascii_bin'])]
    private string $contactValue;
    #[ORM\Column(name: 'account_id', type: UuidBinaryType::NAME, nullable: true)]
    private UuidInterface|null $accountId      = null;
    #[ORM\Column(name: 'actor_id', type: UuidBinaryType::NAME, nullable: true)]
    private UuidInterface|null $actorId        = null;
    #[ORM\Column(name: 'verified_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private DateTimeImmutable|null $verifiedAt = null;
    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $state;

    public static function create(Contact $contact): self
    {
        $record       = new self();
        $id           = $contact->id();
        $record->id   = Uuid::fromString($id);
        $record->uuid = $id;
        $record->synchronize($contact);

        return $record;
    }

    public function synchronize(Contact $contact): void
    {
        $state = $contact->snapshot();
        if ($state['revision'] !== $this->version) {
            throw IdentityConflict::create('The contact verification changed.');
        }

        $contactState       = ContactState::fromArray($state);
        $this->contextHash  = $state['contextHash'];
        $this->realm        = $state['realm'];
        $this->purpose      = $state['purpose'];
        $this->contactType  = $state['contactType'];
        $this->contactValue = $state['contactValue'];
        $this->accountId    = $state['accountId'] === null ? null : Uuid::fromString($state['accountId']);
        $this->actorId      = $state['actorId'] === null ? null : Uuid::fromString($state['actorId']);
        $this->verifiedAt   = $state['verifiedAt'] === null ? null : new DateTimeImmutable($state['verifiedAt'], new DateTimeZone('UTC'));
        $this->replaceState($contactState);
    }

    public function toDomain(): Contact
    {
        $state    = $this->state();
        $account  = $this->accountId;
        $actor    = $this->actorId;
        $verified = $this->verifiedAt;

        return new Contact(IdentityId::fromString($this->id()), [
            'contextHash' => $this->contextHash,
            'realm' => $this->realm,
            'purpose' => $this->purpose,
            'contactType' => $this->contactType,
            'contactValue' => $this->contactValue,
            'accountId' => $account?->toString(),
            'actorId' => $actor?->toString(),
            'verifiedAt' => $verified?->format('Y-m-d H:i:s.u'),
            'generationId' => $state->generationId,
            'codeHash' => $state->codeHash,
            'codeExpiresAt' => $state->codeExpiresAt,
            'failedAttempts' => $state->failedAttempts,
            'requestedAt' => $state->requestedAt,
            'lastSentAt' => $state->lastSentAt,
            'consumedAt' => $state->consumedAt,
            'deliveryState' => $state->deliveryState,
            'revision' => $this->version,
        ]);
    }

    public function state(): ContactState
    {
        return ContactState::fromArray($this->state);
    }

    public function replaceState(ContactState $state): void
    {
        $this->state = $state->toArray();
    }

    public function id(): string
    {
        $id = $this->id;

        return $id->toString();
    }
}
