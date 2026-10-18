<?php

declare(strict_types=1);

namespace Tests\Support\Services;

use PHPUnit\Framework\Assert;
use Redis;

final class RedisAuthentication
{
    public static function verify(Redis $client, string $password): void
    {
        Assert::assertTrue($password === '' || $client->auth($password));
    }
}
