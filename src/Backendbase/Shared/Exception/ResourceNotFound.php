<?php

declare(strict_types=1);

namespace Backendbase\Shared\Exception;

use Backendbase\Shared\Domain\Exception\DomainException;

class ResourceNotFound extends DomainException
{
    public const int STATUS   = 404;
    public const string TYPE  = 'about:blank';
    public const string CODE  = 'general/resource-not-found';
    public const string TITLE = 'Resource Not Found';
}
