<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Exception;

use Backendbase\Shared\Domain\Exception\DomainException;

class AuthorizationExpired extends DomainException
{
    public const int STATUS   = 401;
    public const string TYPE  = 'about:blank';
    public const string CODE  = 'identity-access/authorization-expired';
    public const string TITLE = 'Authorization Expired';
}
