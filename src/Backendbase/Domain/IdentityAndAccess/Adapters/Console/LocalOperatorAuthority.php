<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Console;

use Backendbase\Shared\Exception\ResourceAccessForbidden;

final class LocalOperatorAuthority
{
    public static function assertRoot(string $interface, int|null $effectiveUserId): void
    {
        if ($interface !== 'cli' || $effectiveUserId !== 0) {
            throw ResourceAccessForbidden::create('An authorized local root CLI process is required to apply identity operator actions.');
        }
    }
}
