<?php

declare(strict_types=1);

namespace Tests\Support\Services\RedisFailover;

use RuntimeException;

use function microtime;
use function usleep;

final class Eventually
{
    public static function until(callable $condition, string $description, float $seconds = 20): void
    {
        $deadline = microtime(true) + $seconds;
        do {
            if ($condition()) {
                return;
            }

            usleep(100000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('Redis failover deadline exceeded: ' . $description);
    }

    public static function throughout(callable $condition, string $description, float $seconds = 3): void
    {
        $deadline = microtime(true) + $seconds;
        do {
            if (! $condition()) {
                throw new RuntimeException('Redis failover invariant failed: ' . $description);
            }

            usleep(100000);
        } while (microtime(true) < $deadline);
    }
}
