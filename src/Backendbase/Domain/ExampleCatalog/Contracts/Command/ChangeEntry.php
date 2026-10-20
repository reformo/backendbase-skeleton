<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Contracts\Command;

use Backendbase\Domain\ExampleCatalog\Contracts\Input\EntryChanges;
use Backendbase\Domain\ExampleCatalog\Domain\EntryIdentity;
use Backendbase\Domain\ExampleCatalog\Domain\Primitives\EntryDetails;
use Backendbase\Domain\ExampleCatalog\Domain\Primitives\EntryValue;
use Backendbase\Shared\CQRS\Command;
use Override;

class ChangeEntry implements Command
{
    private EntryChanges $changes;

    public function __construct(
        private EntryIdentity $identity,
    ) {
        $this->changes = new EntryChanges();
    }

    public function identity(): EntryIdentity
    {
        return $this->identity;
    }

    public function isActive(): bool|null
    {
        $changes = $this->changes;

        return $changes->isActive;
    }

    public function setIsActive(bool|null $isActive): self
    {
        $changes       = $this->changes;
        $value         = $changes->value;
        $details       = $changes->details;
        $this->changes = new EntryChanges($isActive, $value, $details);

        return $this;
    }

    public function value(): string|null
    {
        $changes = $this->changes;
        $value   = $changes->value;

        return $value?->toString();
    }

    public function setValue(string|null $value): self
    {
        $changes       = $this->changes;
        $isActive      = $changes->isActive;
        $details       = $changes->details;
        $value         = $value === null ? null : new EntryValue($value);
        $this->changes = new EntryChanges($isActive, $value, $details);

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function details(): array|null
    {
        $changes = $this->changes;
        $details = $changes->details;

        return $details?->toArray();
    }

    /** @param array<array-key, mixed>|null $details */
    public function setDetails(array|null $details): self
    {
        $changes       = $this->changes;
        $isActive      = $changes->isActive;
        $value         = $changes->value;
        $details       = $details === null ? null : new EntryDetails($details);
        $this->changes = new EntryChanges($isActive, $value, $details);

        return $this;
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $identity       = $this->identity;
        $identityValues = $identity->toArray();
        $isActive       = $this->isActive();
        $value          = $this->value();
        $details        = $this->details();

        return ['identity' => $identityValues, 'isActive' => $isActive, 'value' => $value, 'details' => $details];
    }
}
