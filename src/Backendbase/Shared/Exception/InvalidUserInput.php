<?php

declare(strict_types=1);

namespace Backendbase\Shared\Exception;

use Backendbase\Shared\Domain\Exception\DomainException;

class InvalidUserInput extends DomainException
{
    public const int STATUS   = 400;
    public const string TYPE  = 'about:blank';
    public const string CODE  = 'general/invalid-user-input';
    public const string TITLE = 'Invalid user input provided';
}
