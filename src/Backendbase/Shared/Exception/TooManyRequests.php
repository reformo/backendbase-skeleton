<?php

declare(strict_types=1);

namespace Backendbase\Shared\Exception;

use Backendbase\Shared\Domain\Exception\DomainException;

class TooManyRequests extends DomainException
{
    public const int STATUS   = 409;
    public const string TYPE  = 'about:blank';
    public const string CODE  = 'system/too-many-requests';
    public const string TITLE = 'Too many requests';
}
