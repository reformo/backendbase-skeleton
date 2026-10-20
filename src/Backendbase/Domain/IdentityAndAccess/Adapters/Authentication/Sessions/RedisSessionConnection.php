<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Sessions;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\SessionState;
use Redislabs\Module\RedisJson\RedisJsonInterface;
use Throwable;

use function is_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final readonly class RedisSessionConnection
{
    public function __construct(private RedisJsonInterface $redisJson)
    {
    }

    /** @param list<string|int> $arguments */
    public function evaluate(string $script, array $arguments, int $keyCount): mixed
    {
        try {
            $redisJson = $this->redisJson;
            $result    = $redisJson->raw('EVAL', $script, $keyCount, ...$arguments);
            if ($result === false) {
                throw AuthenticationUnavailable::create('Authentication state is temporarily unavailable.');
            }

            return $result;
        } catch (Throwable $exception) {
            throw AuthenticationUnavailable::create('Authentication state is temporarily unavailable.', previous: $exception);
        }
    }

    public static function decode(string $value): SessionState
    {
        try {
            $values = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($values)) {
                throw AuthenticationUnavailable::create('The stored session state is invalid.');
            }

            return SessionState::fromArray($values);
        } catch (Throwable $exception) {
            throw AuthenticationUnavailable::create('The stored session state is invalid.', previous: $exception);
        }
    }
}
