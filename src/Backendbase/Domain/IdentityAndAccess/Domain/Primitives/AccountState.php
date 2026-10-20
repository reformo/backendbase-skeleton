<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Primitives;

use JsonSerializable;
use Override;

use function array_key_exists;

final readonly class AccountState implements JsonSerializable
{
    private function __construct(
        public bool $ownershipVerified,
        public bool $enabled,
        public int $authVersion,
        public bool $registrationAccepted,
    ) {
    }

    public function with(bool|null $ownershipVerified = null, bool|null $enabled = null, int|null $authVersion = null, bool|null $registrationAccepted = null): self
    {
        $ownershipVerified    ??= $this->ownershipVerified;
        $enabled              ??= $this->enabled;
        $authVersion          ??= $this->authVersion;
        $registrationAccepted ??= $this->registrationAccepted;

        return new self($ownershipVerified, $enabled, $authVersion, $registrationAccepted);
    }

    /** @param array<array-key, mixed> $values */
    public static function fromArray(array $values): self
    {
        StateField::assertKnownFields($values, ['ownershipVerified', 'enabled', 'authVersion', 'registrationAccepted']);

        return new self(
            StateField::boolean($values, 'ownershipVerified'),
            StateField::boolean($values, 'enabled'),
            StateField::integer($values, 'authVersion'),
            StateField::boolean($values, array_key_exists('registrationAccepted', $values) ? 'registrationAccepted' : 'ownershipVerified'),
        );
    }

    /** @return array{ownershipVerified: bool, enabled: bool, authVersion: int, registrationAccepted: bool} */
    public function toArray(): array
    {
        return [
            'ownershipVerified' => $this->ownershipVerified,
            'enabled' => $this->enabled,
            'authVersion' => $this->authVersion,
            'registrationAccepted' => $this->registrationAccepted,
        ];
    }

    /** @return array{ownershipVerified: bool, enabled: bool, authVersion: int, registrationAccepted: bool} */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
