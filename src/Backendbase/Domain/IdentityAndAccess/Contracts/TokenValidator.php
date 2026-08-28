<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

interface TokenValidator
{
    /** @return array<string, mixed> */
    public function validateToken(string $jwtToken): array;

    /** @return array<string, mixed> */
    public function validateByUserId(string $userId): array;

    public function revokeToken(string $jwtToken): void;
}
