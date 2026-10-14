<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\Input;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use DateTimeImmutable;

final readonly class AccountClosure
{
    public function __construct(private Principal $principal, private DateTimeImmutable $now)
    {
    }

    public function principal(): Principal
    {
        return $this->principal;
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
