<?php

declare(strict_types=1);

namespace Backendbase\Shared\Exception;

use Backendbase\Shared\Domain\Exception\DomainException;

class ResourceAccessForbidden extends DomainException
{
    public const int STATUS   = 403;
    public const string TYPE  = 'about:blank';
    public const string CODE  = 'identity-access/restricted';
    public const string TITLE = 'Forbidden Resource Access';
}
