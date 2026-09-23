<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Http;

use Backendbase\Domain\ExampleCatalog\Domain\Exception\EntryAlreadyExists;
use Backendbase\Domain\IdentityAndAccess\Exception\AccountAlreadyRegistered;
use Backendbase\Domain\IdentityAndAccess\Exception\AuthorizationExpired;
use Backendbase\Domain\IdentityAndAccess\Exception\InvalidCredentials;
use Backendbase\Domain\IdentityAndAccess\Exception\UnknownAccountPrivilege;
use Backendbase\Infrastructure\Adapters\Http\DomainErrorProblemDetailsMapper;
use Backendbase\Shared\Domain\Exception\DomainException;
use Backendbase\Shared\Domain\Exception\DomainRecordNotFound;
use Backendbase\Shared\Exception\CommandFailed;
use Backendbase\Shared\Exception\InvalidResourceId;
use Backendbase\Shared\Exception\InvalidUserInput;
use Backendbase\Shared\Exception\ResourceAccessForbidden;
use Backendbase\Shared\Exception\ResourceNotFound;
use Backendbase\Shared\Exception\TooManyRequests;
use Backendbase\Shared\Primitives\Exception\InvalidEmailAddress;
use Backendbase\Shared\Primitives\Exception\InvalidName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DomainErrorProblemDetailsMapperTest extends TestCase
{
    /** @param array{status: int, title: string, code: string, type: string} $expected */
    #[DataProvider('mappedErrors')]
    #[Test]
    public function itMapsDomainErrorTypesToProblemDetails(DomainException $exception, array $expected): void
    {
        $payload = (new DomainErrorProblemDetailsMapper())->map($exception)->jsonSerialize();

        self::assertSame($expected['status'], $payload['status']);
        self::assertSame($expected['title'], $payload['title']);
        self::assertSame($expected['code'], $payload['code']);
        self::assertSame($expected['type'], $payload['type']);
        self::assertSame('Failure.', $payload['detail']);
        self::assertSame('example-id', $payload['resourceId']);
    }

    /**
     * @return iterable<string, array{
     *     DomainException,
     *     array{status: int, title: string, code: string, type: string}
     * }>
     */
    public static function mappedErrors(): iterable
    {
        $context = ['resourceId' => 'example-id'];

        yield 'example already exists' => [
            EntryAlreadyExists::create('Failure.', $context),
            [
                'status' => 409,
                'title' => 'Example Already Exists',
                'code' => 'example/already-exists',
                'type' => 'about:blank',
            ],
        ];

        yield 'authorization expired' => [
            AuthorizationExpired::create('Failure.', $context),
            [
                'status' => 401,
                'title' => 'Authorization Expired',
                'code' => 'identity-access/authorization-expired',
                'type' => 'about:blank',
            ],
        ];

        yield 'account already registered' => [
            AccountAlreadyRegistered::create('Failure.', $context),
            [
                'status' => 409,
                'title' => 'Account Already Registered',
                'code' => 'identity-access/account-already-registered',
                'type' => 'about:blank',
            ],
        ];

        yield 'invalid credentials' => [
            InvalidCredentials::create('Failure.', $context),
            [
                'status' => 401,
                'title' => 'Authentication Failed',
                'code' => 'identity-access/invalid-credentials',
                'type' => 'about:blank',
            ],
        ];

        yield 'unknown account privilege' => [
            UnknownAccountPrivilege::create('Failure.', $context),
            [
                'status' => 400,
                'title' => 'Unknown Account Privilege',
                'code' => 'identity-access/unknown-account-privilege',
                'type' => 'about:blank',
            ],
        ];

        yield 'command failed' => [
            CommandFailed::create('Failure.', $context),
            [
                'status' => 500,
                'title' => 'Command failed',
                'code' => 'general/command-failed',
                'type' => 'about:blank',
            ],
        ];

        yield 'invalid resource identifier' => [
            InvalidResourceId::create('Failure.', $context),
            [
                'status' => 400,
                'title' => 'Invalid resource id',
                'code' => 'general/invalid-resource-id',
                'type' => 'about:blank',
            ],
        ];

        yield 'invalid user input' => [
            InvalidUserInput::create('Failure.', $context),
            [
                'status' => 400,
                'title' => 'Invalid user input provided',
                'code' => 'general/invalid-user-input',
                'type' => 'about:blank',
            ],
        ];

        yield 'forbidden access' => [
            ResourceAccessForbidden::create('Failure.', $context),
            [
                'status' => 403,
                'title' => 'Forbidden Resource Access',
                'code' => 'identity-access/restricted',
                'type' => 'about:blank',
            ],
        ];

        yield 'resource not found' => [
            ResourceNotFound::create('Failure.', $context),
            [
                'status' => 404,
                'title' => 'Resource Not Found',
                'code' => 'general/resource-not-found',
                'type' => 'about:blank',
            ],
        ];

        yield 'too many requests' => [
            TooManyRequests::create('Failure.', $context),
            [
                'status' => 409,
                'title' => 'Too many requests',
                'code' => 'system/too-many-requests',
                'type' => 'about:blank',
            ],
        ];

        yield 'invalid email address' => [
            InvalidEmailAddress::create('Failure.', $context),
            [
                'status' => 400,
                'title' => 'Invalid Email Address',
                'code' => 'email/invalid-email-address',
                'type' => 'https://httpstatus.es/400',
            ],
        ];

        yield 'invalid name' => [
            InvalidName::create('Failure.', $context),
            [
                'status' => 400,
                'title' => 'Invalid Name',
                'code' => 'name/invalid-name',
                'type' => 'https://httpstatus.es/400',
            ],
        ];

        yield 'domain record not found' => [
            DomainRecordNotFound::create('Failure.', $context),
            [
                'status' => 404,
                'title' => 'NotFound',
                'code' => 'domain/not-found',
                'type' => 'about:blank',
            ],
        ];
    }
}
