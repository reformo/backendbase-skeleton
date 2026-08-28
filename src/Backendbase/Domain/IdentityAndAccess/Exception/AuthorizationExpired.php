<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Exception;

use Backendbase\Shared\Domain\Exception\DomainException;

class AuthorizationExpired extends DomainException
{
}
