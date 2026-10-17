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

class AddEntry implements Command
{
    private EntryIdentity $identity;
    /** @var array{entryId: EntryId, isActive: bool, value: EntryValue, details: EntryDetails|null} */
    private array $values;

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
        $this->identity = new EntryIdentity($type, $typeTargetId, $group, $key);
        $this->values   = [
            'entryId' => EntryId::fromString($entryId),
            'isActive' => $isActive,
            'value' => new EntryValue($value),
            'details' => $details === null ? null : new EntryDetails($details),
        ];
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        $details = $this->values['details'];

        return $details?->toArray() ?? [];
    }

    public function entryId(): string
    {
        $identifier = $this->values['entryId'];

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
        return $this->values['isActive'];
    }

    public function key(): string
    {
        $identity = $this->identity;

        return $identity->key();
    }

    public function value(): string
    {
        $value = $this->values['value'];

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
        $details      = $this->values['details'];
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
