<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Primitives;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;

final readonly class ActorSnapshot
{
    public function __construct(
        public string $accountId,
        public ActorState $authorization,
        public string|null $deletedAt,
        public int $revision,
        public string $createdAt,
    ) {
        IdentityId::fromString($accountId);
    }

    /** @param array<array-key, mixed> $values */
    public static function fromArray(array $values): self
    {
        StateField::assertKnownFields($values, ['accountId', 'approved', 'invitation', 'approval', 'deletedAt', 'revision', 'createdAt']);
        $authorization = ActorState::fromArray([
            'approved' => StateField::boolean($values, 'approved'),
            'invitation' => StateField::nullableString($values, 'invitation'),
            'approval' => StateField::nullableString($values, 'approval'),
        ]);

        return new self(
            StateField::string($values, 'accountId'),
            $authorization,
            StateField::nullableString($values, 'deletedAt'),
            StateField::integer($values, 'revision'),
            StateField::string($values, 'createdAt'),
        );
    }

    public function withAuthorization(ActorState $authorization): self
    {
        $accountId = $this->accountId;
        $deletedAt = $this->deletedAt;
        $revision  = $this->revision;
        $createdAt = $this->createdAt;

        return new self($accountId, $authorization, $deletedAt, $revision, $createdAt);
    }

    public function withDeletedAt(string|null $deletedAt): self
    {
        $accountId     = $this->accountId;
        $authorization = $this->authorization;
        $revision      = $this->revision;
        $createdAt     = $this->createdAt;

        return new self($accountId, $authorization, $deletedAt, $revision, $createdAt);
    }

    /** @return array{accountId: string, approved: bool, invitation: string|null, approval: string|null, deletedAt: string|null, revision: int, createdAt: string} */
    public function toArray(): array
    {
        $authorization = $this->authorization;
        $accountId     = $this->accountId;
        $deletedAt     = $this->deletedAt;
        $revision      = $this->revision;
        $createdAt     = $this->createdAt;

        return ['accountId' => $accountId, ...$authorization->toArray(), 'deletedAt' => $deletedAt, 'revision' => $revision, 'createdAt' => $createdAt];
    }
}
