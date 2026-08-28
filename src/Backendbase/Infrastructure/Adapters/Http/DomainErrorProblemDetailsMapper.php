<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http;

use Backendbase\Domain\ExampleBoundedContext\Domain\Exception\ExampleAlreadyExists;
use Backendbase\Domain\IdentityAndAccess\Exception\AuthorizationExpired;
use Backendbase\Shared\Domain\Exception\DomainException;
use Backendbase\Shared\Domain\Exception\DomainRecordNotFound;
use Backendbase\Shared\Exception\CommandFailed;
use Backendbase\Shared\Exception\InvalidResourceId;
use Backendbase\Shared\Exception\InvalidUserInput;
use Backendbase\Shared\Exception\ResourceAccessForbidden;
use Backendbase\Shared\Exception\ResourceNotFound;
use Backendbase\Shared\Exception\TooManyRequests;
use Backendbase\Shared\Http\Actions\ActionError;
use Backendbase\Shared\Primitives\Exception\InvalidEmailAddress;
use Backendbase\Shared\Primitives\Exception\InvalidName;

final class DomainErrorProblemDetailsMapper
{
    public function map(DomainException $exception): ActionError
    {
        [$status, $title, $code, $type] = match (true) {
            $exception instanceof ExampleAlreadyExists => [
                409,
                'Example Already Exists',
                'example/already-exists',
                'about:blank',
            ],
            $exception instanceof AuthorizationExpired => [
                401,
                'Authorization Expired',
                'identity-access/authorization-expired',
                'about:blank',
            ],
            $exception instanceof CommandFailed => [500, 'Command failed', 'general/command-failed', 'about:blank'],
            $exception instanceof InvalidResourceId => [
                400,
                'Invalid resource id',
                'general/invalid-resource-id',
                'about:blank',
            ],
            $exception instanceof InvalidUserInput => [
                400,
                'Invalid user input provided',
                'general/invalid-user-input',
                'about:blank',
            ],
            $exception instanceof ResourceAccessForbidden => [
                403,
                'Forbidden Resource Access',
                'identity-access/restricted',
                'about:blank',
            ],
            $exception instanceof ResourceNotFound => [
                404,
                'Resource Not Found',
                'general/resource-not-found',
                'about:blank',
            ],
            $exception instanceof TooManyRequests => [
                409,
                'Too many requests',
                'system/too-many-requests',
                'about:blank',
            ],
            $exception instanceof InvalidEmailAddress => [
                400,
                'Invalid Email Address',
                'email/invalid-email-address',
                'https://httpstatus.es/400',
            ],
            $exception instanceof InvalidName => [
                400,
                'Invalid Name',
                'name/invalid-name',
                'https://httpstatus.es/400',
            ],
            $exception instanceof DomainRecordNotFound => [
                404,
                'NotFound',
                'domain/not-found',
                'about:blank',
            ],
            default => [500, 'Server Error', 'server/server-error', 'about:blank'],
        };

        return new ActionError(
            $status,
            $title,
            $code,
            $type,
            $exception->getMessage(),
            $exception->context(),
        );
    }
}
