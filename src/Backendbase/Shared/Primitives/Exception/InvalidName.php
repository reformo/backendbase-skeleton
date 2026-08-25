<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives\Exception;

use Backendbase\Shared\Domain\Exception\DomainException;

class InvalidName extends DomainException
{
    protected int $statusCode = 400;
    /** @phpstan-var string */
    protected $code        = 'name/invalid-name';
    protected string $type = 'https://httpstatus.es/400';
}
