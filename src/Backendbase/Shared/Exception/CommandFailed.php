<?php

declare(strict_types=1);

namespace Backendbase\Shared\Exception;

use Backendbase\Shared\Domain\Exception\DomainException;

class CommandFailed extends DomainException
{
    public const int STATUS   = 500;
    public const string TYPE  = 'about:blank';
    public const string CODE  = 'general/command-failed';
    public const string TITLE = 'Command failed';
}
