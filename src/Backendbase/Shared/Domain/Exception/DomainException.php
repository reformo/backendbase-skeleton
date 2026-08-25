<?php

declare(strict_types=1);

namespace Backendbase\Shared\Domain\Exception;

use Backendbase\Shared\ProblemDetailsException;
use DomainException as PhpDomainException;

abstract class DomainException extends PhpDomainException implements ProblemDetailsException
{
    use DomainExceptionProblemDetails;

    public const int STATUS   = 500;
    public const string TYPE  = 'about:blank';
    public const string CODE  = 'server/server-error';
    public const string TITLE = 'Server Error';
}
