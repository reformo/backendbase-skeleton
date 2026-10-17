<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Primitives;

use JsonSerializable;
use Override;

final readonly class ContactState implements JsonSerializable
{
    private function __construct(
        public string $realm,
        public string $purpose,
        public string $contactType,
        public string $contactValue,
        public string $contextHash,
        public string|null $accountId,
        public string|null $actorId,
        public string $generationId,
        public string $codeHash,
        public string $codeExpiresAt,
        public int $failedAttempts,
        public string $requestedAt,
        public string $lastSentAt,
        public string|null $verifiedAt,
        public string|null $consumedAt,
        public string $deliveryState,
        public int $revision,
    ) {
    }

    /** @param array<array-key, mixed> $values */
    public static function fromArray(array $values): self
    {
        StateField::assertKnownFields($values, ['realm', 'purpose', 'contactType', 'contactValue', 'contextHash', 'accountId', 'actorId', 'generationId', 'codeHash', 'codeExpiresAt', 'failedAttempts', 'requestedAt', 'lastSentAt', 'verifiedAt', 'consumedAt', 'deliveryState', 'revision']);

        return new self(
            StateField::string($values, 'realm'),
            StateField::string($values, 'purpose'),
            StateField::string($values, 'contactType'),
            StateField::string($values, 'contactValue'),
            StateField::string($values, 'contextHash'),
            StateField::nullableString($values, 'accountId'),
            StateField::nullableString($values, 'actorId'),
            StateField::string($values, 'generationId'),
            StateField::string($values, 'codeHash'),
            StateField::string($values, 'codeExpiresAt'),
            StateField::integer($values, 'failedAttempts'),
            StateField::string($values, 'requestedAt'),
            StateField::string($values, 'lastSentAt'),
            StateField::nullableString($values, 'verifiedAt'),
            StateField::nullableString($values, 'consumedAt'),
            StateField::string($values, 'deliveryState'),
            StateField::integer($values, 'revision'),
        );
    }

    /** @return array{realm: string, purpose: string, contactType: string, contactValue: string, contextHash: string, accountId: string|null, actorId: string|null, generationId: string, codeHash: string, codeExpiresAt: string, failedAttempts: int, requestedAt: string, lastSentAt: string, verifiedAt: string|null, consumedAt: string|null, deliveryState: string, revision: int} */
    public function toArray(): array
    {
        return [
            'realm' => $this->realm,
            'purpose' => $this->purpose,
            'contactType' => $this->contactType,
            'contactValue' => $this->contactValue,
            'contextHash' => $this->contextHash,
            'accountId' => $this->accountId,
            'actorId' => $this->actorId,
            'generationId' => $this->generationId,
            'codeHash' => $this->codeHash,
            'codeExpiresAt' => $this->codeExpiresAt,
            'failedAttempts' => $this->failedAttempts,
            'requestedAt' => $this->requestedAt,
            'lastSentAt' => $this->lastSentAt,
            'verifiedAt' => $this->verifiedAt,
            'consumedAt' => $this->consumedAt,
            'deliveryState' => $this->deliveryState,
            'revision' => $this->revision,
        ];
    }

    /** @return array{realm: string, purpose: string, contactType: string, contactValue: string, contextHash: string, accountId: string|null, actorId: string|null, generationId: string, codeHash: string, codeExpiresAt: string, failedAttempts: int, requestedAt: string, lastSentAt: string, verifiedAt: string|null, consumedAt: string|null, deliveryState: string, revision: int} */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
