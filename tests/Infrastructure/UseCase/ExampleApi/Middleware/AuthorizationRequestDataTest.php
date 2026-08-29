<?php

declare(strict_types=1);

namespace Tests\Infrastructure\UseCase\ExampleApi\Middleware;

use Backendbase\Infrastructure\UseCase\ExampleApi\Middleware\AuthorizationRequestData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuthorizationRequestDataTest extends TestCase
{
    #[Test]
    public function itRejectsMalformedPrivilegeState(): void
    {
        self::assertNull(AuthorizationRequestData::fromToken([
            'user' => ['uuid' => '4bb3fe29-8b80-463e-9d42-b3a9298a7586'],
            'privileges' => ['account.list', 'account.list'],
        ]));
    }

    #[Test]
    public function itRejectsAnInvalidTimezone(): void
    {
        self::assertNull(AuthorizationRequestData::timezone('invalid/timezone'));
        self::assertNull(AuthorizationRequestData::timezone(''));
    }

    #[Test]
    public function itRejectsMalformedUserAndPrivilegeValues(): void
    {
        self::assertNotNull(AuthorizationRequestData::fromToken([
            'user' => [
                'uuid' => '4bb3fe29-8b80-463e-9d42-b3a9298a7586',
                'email' => 'account@example.com',
            ],
            'privileges' => [],
        ]));
        self::assertNull(AuthorizationRequestData::fromToken([
            'user' => ['uuid' => 'invalid-uuid'],
            'privileges' => [],
        ]));
        self::assertNull(AuthorizationRequestData::fromToken([
            'user' => ['uuid' => '4bb3fe29-8b80-463e-9d42-b3a9298a7586', 0 => 'invalid-key'],
            'privileges' => [],
        ]));
        self::assertNull(AuthorizationRequestData::fromToken([
            'user' => ['uuid' => '4bb3fe29-8b80-463e-9d42-b3a9298a7586', 'nested' => []],
            'privileges' => [],
        ]));
        self::assertNull(AuthorizationRequestData::fromToken([
            'user' => ['uuid' => '4bb3fe29-8b80-463e-9d42-b3a9298a7586'],
            'privileges' => 'invalid',
        ]));
    }
}
