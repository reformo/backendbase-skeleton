<?php

declare(strict_types=1);

namespace Backendbase\Shared\Exception;

use Backendbase\Shared\Domain\Exception\DomainException;

class InvalidResourceId extends DomainException
{
    public const int STATUS   = 400;
    public const string TYPE  = 'about:blank';
    public const string CODE  = 'general/invalid-resource-id';
    public const string TITLE = 'Invalid resource id';
}
