<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives\Exception;

use Backendbase\Shared\Domain\Exception\DomainException;

class InvalidEmailAddress extends DomainException
{
    protected int $statusCode = 400;
    /** @phpstan-var string */
    protected $code        = 'email/invalid-email-address';
    protected string $type = 'https://httpstatus.es/400';
}
