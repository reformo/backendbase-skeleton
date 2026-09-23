<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http;

use Backendbase\Domain\ExampleCatalog\Domain\Exception\EntryAlreadyExists;
use Backendbase\Domain\IdentityAndAccess\Exception\AccountAlreadyRegistered;
use Backendbase\Domain\IdentityAndAccess\Exception\AuthorizationExpired;
use Backendbase\Domain\IdentityAndAccess\Exception\InvalidCredentials;
use Backendbase\Domain\IdentityAndAccess\Exception\UnknownAccountPrivilege;
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
            $exception instanceof EntryAlreadyExists => [
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
            $exception instanceof InvalidCredentials => [
                401,
                'Authentication Failed',
                'identity-access/invalid-credentials',
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
            default => self::fallbackDetails($exception),
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

    /** @return array{int, string, string, string} */
    private static function fallbackDetails(DomainException $exception): array
    {
        return match (true) {
            $exception instanceof AccountAlreadyRegistered => [
                409,
                'Account Already Registered',
                'identity-access/account-already-registered',
                'about:blank',
            ],
            $exception instanceof UnknownAccountPrivilege => [
                400,
                'Unknown Account Privilege',
                'identity-access/unknown-account-privilege',
                'about:blank',
            ],
            $exception instanceof InvalidEmailAddress => [
                400,
                'Invalid Email Address',
                'email/invalid-email-address',
                'https://httpstatus.es/400',
            ],
            $exception instanceof InvalidName => [400, 'Invalid Name', 'name/invalid-name', 'https://httpstatus.es/400'],
            $exception instanceof DomainRecordNotFound => [404, 'NotFound', 'domain/not-found', 'about:blank'],
            default => [500, 'Server Error', 'server/server-error', 'about:blank'],
        };
    }
}
