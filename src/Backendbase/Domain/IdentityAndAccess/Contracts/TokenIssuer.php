<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

interface TokenIssuer
{
    /** @param array<string, mixed> $data */
    public function issueNewToken(string $claimKey, mixed $claimValue, array $data): string;
}
