<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Primitives;

use JsonSerializable;
use Override;

final readonly class AccountState implements JsonSerializable
{
    private function __construct(
        public bool $ownershipVerified,
        public bool $enabled,
        public int $authVersion,
    ) {
    }

    /** @param array<array-key, mixed> $values */
    public static function fromArray(array $values): self
    {
        StateField::assertKnownFields($values, ['ownershipVerified', 'enabled', 'authVersion']);

        return new self(
            StateField::boolean($values, 'ownershipVerified'),
            StateField::boolean($values, 'enabled'),
            StateField::integer($values, 'authVersion'),
        );
    }

    /** @return array{ownershipVerified: bool, enabled: bool, authVersion: int} */
    public function toArray(): array
    {
        return [
            'ownershipVerified' => $this->ownershipVerified,
            'enabled' => $this->enabled,
            'authVersion' => $this->authVersion,
        ];
    }

    /** @return array{ownershipVerified: bool, enabled: bool, authVersion: int} */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
