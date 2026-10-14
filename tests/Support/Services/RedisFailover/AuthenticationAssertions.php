<?php

declare(strict_types=1);

namespace Tests\Support\Services\RedisFailover;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use PHPUnit\Framework\Assert;

final class AuthenticationAssertions
{
    public static function denied(callable $work): void
    {
        try {
            $work();
            Assert::fail('Authentication must be denied.');
        } catch (AuthenticationDenied $exception) {
            Assert::assertSame('Authentication is not permitted.', $exception->getMessage());
        }
    }

    public static function unavailable(callable $work): void
    {
        try {
            $work();
            Assert::fail('Authentication state must be unavailable.');
        } catch (AuthenticationUnavailable $exception) {
            Assert::assertSame('Authentication state is temporarily unavailable.', $exception->getMessage());
        }
    }
}
