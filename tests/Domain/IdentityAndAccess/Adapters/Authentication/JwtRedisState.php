<?php

declare(strict_types=1);

namespace Tests\Domain\IdentityAndAccess\Adapters\Authentication;

final class JwtRedisState
{
    /** @var array<string, mixed> */
    public array $jsonValues = [];

    /** @param array<string, mixed> $value */
    public function put(string $key, array $value): void
    {
        $this->jsonValues[$key] = $value;
    }
}
