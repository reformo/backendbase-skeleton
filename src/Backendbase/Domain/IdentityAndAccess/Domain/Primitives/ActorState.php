<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Primitives;

use JsonSerializable;
use Override;

final readonly class ActorState implements JsonSerializable
{
    private function __construct(
        public bool $approved,
        public string|null $invitation,
        public string|null $approval,
    ) {
    }

    public function withApproval(bool $approved, string $approval): self
    {
        return new self($approved, $this->invitation, $approval);
    }

    /** @param array<array-key, mixed> $values */
    public static function fromArray(array $values): self
    {
        StateField::assertKnownFields($values, ['approved', 'invitation', 'approval']);

        return new self(
            StateField::boolean($values, 'approved'),
            StateField::nullableString($values, 'invitation'),
            StateField::nullableString($values, 'approval'),
        );
    }

    /** @return array{approved: bool, invitation: string|null, approval: string|null} */
    public function toArray(): array
    {
        return [
            'approved' => $this->approved,
            'invitation' => $this->invitation,
            'approval' => $this->approval,
        ];
    }

    /** @return array{approved: bool, invitation: string|null, approval: string|null} */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
