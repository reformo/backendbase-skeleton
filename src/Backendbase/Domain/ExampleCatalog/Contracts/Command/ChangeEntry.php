<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Contracts\Command;

use Backendbase\Domain\ExampleCatalog\Domain\EntryIdentity;
use Backendbase\Domain\ExampleCatalog\Domain\Primitives\EntryDetails;
use Backendbase\Domain\ExampleCatalog\Domain\Primitives\EntryValue;
use Backendbase\Shared\CQRS\Command;
use Override;

class ChangeEntry implements Command
{
    /** @var array{isActive: bool|null, value: EntryValue|null, details: EntryDetails|null} */
    private array $changes = ['isActive' => null, 'value' => null, 'details' => null];

    public function __construct(
        private EntryIdentity $identity,
    ) {
    }

    public function identity(): EntryIdentity
    {
        return $this->identity;
    }

    public function isActive(): bool|null
    {
        return $this->changes['isActive'];
    }

    public function setIsActive(bool|null $isActive): self
    {
        $this->changes['isActive'] = $isActive;

        return $this;
    }

    public function value(): string|null
    {
        $value = $this->changes['value'];

        return $value?->toString();
    }

    public function setValue(string|null $value): self
    {
        $this->changes['value'] = $value === null ? null : new EntryValue($value);

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function details(): array|null
    {
        $details = $this->changes['details'];

        return $details?->toArray();
    }

    /** @param array<array-key, mixed>|null $details */
    public function setDetails(array|null $details): self
    {
        $this->changes['details'] = $details === null ? null : new EntryDetails($details);

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
