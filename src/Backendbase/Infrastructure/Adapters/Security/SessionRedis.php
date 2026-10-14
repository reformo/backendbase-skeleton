<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Security;

use Closure;
use Redis;

final class SessionRedis
{
    private Redis|null $client = null;

    /** @param Closure(): Redis $connect */
    public function __construct(private readonly Closure $connect)
    {
    }

    public function client(): Redis
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $connect      = $this->connect;
        $this->client = $connect();

        return $this->client;
    }
}
