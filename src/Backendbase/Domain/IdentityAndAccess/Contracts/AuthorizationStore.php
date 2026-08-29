<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

use DateTimeImmutable;

/**
 * @phpstan-type IssuedToken array{
 *     token: string,
 *     id: non-empty-string,
 *     issuedAt: DateTimeImmutable,
 *     expiresAt: DateTimeImmutable
 * }
 */
interface AuthorizationStore
{
    /**
     * @param array<string, mixed> $data
     * @param IssuedToken          $issuedToken
     */
    public function store(string $claimKey, string $claimValue, array $data, array $issuedToken): void;

    /** @param non-empty-string $tokenId */
    public function isActive(string $tokenId): bool;

    /** @return array<string, mixed>|null */
    public function byUserId(string $userId): array|null;

    /** @param non-empty-string $tokenId */
    public function revoke(string $tokenId): void;
}
