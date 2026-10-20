<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Contracts\Command;

use Backendbase\Domain\ExampleCatalog\Domain\EntryIdentity;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Domain\ExampleCatalog\Domain\Primitives\EntryDetails;
use Backendbase\Domain\ExampleCatalog\Domain\Primitives\EntryId;
use Backendbase\Domain\ExampleCatalog\Domain\Primitives\EntryValue;
use Backendbase\Shared\CQRS\Command;
use Override;

readonly class AddEntry implements Command
{
    private EntryIdentity $identity;
    private EntryId $identifier;
    private bool $active;
    private EntryValue $entryValue;
    private EntryDetails|null $entryDetails;

    /** @param array<array-key, mixed>|null $details */
    public function __construct(
        string $entryId,
        EntryType $type,
        int|null $typeTargetId,
        string $group,
        bool $isActive,
        string $key,
        string $value,
        array|null $details = [],
    ) {
        $this->identity     = new EntryIdentity($type, $typeTargetId, $group, $key);
        $this->identifier   = EntryId::fromString($entryId);
        $this->active       = $isActive;
        $this->entryValue   = new EntryValue($value);
        $this->entryDetails = $details === null ? null : new EntryDetails($details);
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        $details = $this->entryDetails;

        return $details?->toArray() ?? [];
    }

    public function entryId(): string
    {
        $identifier = $this->identifier;

        return $identifier->id();
    }

    public function type(): EntryType
    {
        $identity = $this->identity;

        return $identity->type();
    }

    public function typeTargetId(): int|null
    {
        $identity = $this->identity;

        return $identity->typeTargetId();
    }

    public function group(): string
    {
        $identity = $this->identity;

        return $identity->group();
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function key(): string
    {
        $identity = $this->identity;

        return $identity->key();
    }

    public function value(): string
    {
        $value = $this->entryValue;

        return $value->toString();
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $type         = $this->type();
        $entryId      = $this->entryId();
        $typeTargetId = $this->typeTargetId();
        $group        = $this->group();
        $isActive     = $this->isActive();
        $key          = $this->key();
        $value        = $this->value();
        $details      = $this->entryDetails;
        $detailValues = $details?->toArray();

        return [
            'exampleId' => $entryId,
            'type' => $type->value,
            'typeTargetId' => $typeTargetId,
            'group' => $group,
            'isActive' => $isActive,
            'key' => $key,
            'value' => $value,
            'details' => $detailValues,
        ];
    }
}
