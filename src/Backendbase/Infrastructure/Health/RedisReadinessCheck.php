<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Health;

use Backendbase\Shared\Health\ReadinessCheck;
use Override;
use Redislabs\Module\RedisJson\RedisJsonInterface;
use UnexpectedValueException;

final readonly class RedisReadinessCheck implements ReadinessCheck
{
    public function __construct(private RedisJsonInterface $redisJson)
    {
    }

    /** @return non-empty-string */
    #[Override]
    public function name(): string
    {
        return 'redis';
    }

    #[Override]
    public function check(): void
    {
        $response = $this->redisJson->raw('PING');
        if ($response !== true && $response !== 'PONG') {
            throw new UnexpectedValueException('The Redis readiness command returned an invalid result.');
        }
    }
}
