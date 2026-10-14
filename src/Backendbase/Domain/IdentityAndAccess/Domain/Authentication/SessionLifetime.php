<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Authentication;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class SessionLifetime
{
    public function __construct(private int $seconds = 900)
    {
        if ($seconds < 60 || $seconds > 3600) {
            throw new InvalidArgumentException('The session lifetime must be between 60 and 3600 seconds.');
        }
    }

    public function expiresAt(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify('+' . $this->seconds . ' seconds');
    }
}
