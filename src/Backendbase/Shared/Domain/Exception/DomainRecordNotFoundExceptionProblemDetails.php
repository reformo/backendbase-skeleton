<?php

declare(strict_types=1);

namespace Backendbase\Shared\Domain\Exception;

use Backendbase\Shared\ProblemDetailsException;
use Exception;

class DomainRecordNotFoundExceptionProblemDetails extends Exception implements ProblemDetailsException
{
    use DomainExceptionProblemDetails;

    public const int STATUS   = 404;
    public const string TYPE  = 'about:blank';
    public const string CODE  = 'domain/not-found';
    public const string TITLE = 'NotFound';
}
