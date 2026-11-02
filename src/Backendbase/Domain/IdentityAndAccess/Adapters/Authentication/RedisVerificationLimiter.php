<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication;

use Backendbase\Domain\IdentityAndAccess\Contracts\VerificationLimiter;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationClient;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationLimited;
use Backendbase\Infrastructure\Adapters\Security\SessionRedis;
use Throwable;

use function count;
use function is_int;

final readonly class RedisVerificationLimiter implements VerificationLimiter
{
    // Check cooldown, contact, and IP limits before reserving either send budget.
    private const string RESERVE_SEND = <<<'LUA'
local contact, ip, cooldown = KEYS[1], KEYS[2], KEYS[3]
local wait = redis.call('TTL', cooldown)
if wait > 0 then return wait end
if tonumber(redis.call('GET', contact) or '0') >= 5 then return math.max(1, redis.call('TTL', contact)) end
if tonumber(redis.call('GET', ip) or '0') >= 20 then return math.max(1, redis.call('TTL', ip)) end
for _, key in ipairs({contact, ip}) do
  if redis.call('INCR', key) == 1 then redis.call('EXPIRE', key, 3600) end
end
redis.call('SET', cooldown, '1', 'EX', 60)
return 0
LUA;

    // Keep each failed or outstanding confirmation in the current one-hour window.
    private const string RESERVE_CONFIRMATION = <<<'LUA'
local count = tonumber(redis.call('GET', KEYS[1]) or '0')
if count >= 10 then return math.max(1, redis.call('TTL', KEYS[1])) end
if redis.call('INCR', KEYS[1]) == 1 then redis.call('EXPIRE', KEYS[1], 3600) end
return 0
LUA;

    // A successful confirmation releases one reservation without extending the window.
    private const string RELEASE_CONFIRMATION = <<<'LUA'
local count = tonumber(redis.call('GET', KEYS[1]) or '0')
if count > 0 then redis.call('DECR', KEYS[1]) end
return 0
LUA;

    public function __construct(private SessionRedis $redis)
    {
    }

    public function send(ContactValue $contact, VerificationClient $client): void
    {
        $key       = $contact->counterKey();
        $clientKey = $client->key();
        $this->run(self::RESERVE_SEND, ['identity-verification:send:' . $key, 'identity-verification:ip:' . $clientKey, 'identity-verification:cooldown:' . $key]);
    }

    public function attempt(ContactValue $contact): void
    {
        $key = $contact->counterKey();
        $this->run(self::RESERVE_CONFIRMATION, ['identity-verification:failure:' . $key]);
    }

    public function accept(ContactValue $contact): void
    {
        $key = $contact->counterKey();
        $this->run(self::RELEASE_CONFIRMATION, ['identity-verification:failure:' . $key]);
    }

    /** @param list<string> $keys */
    private function run(string $script, array $keys): void
    {
        try {
            $connection = $this->redis;
            $client     = $connection->client();
            $result     = $client->eval($script, $keys, count($keys));
            if (! is_int($result) || $result < 0) {
                throw AuthenticationUnavailable::create('Verification request controls are unavailable.');
            }
        } catch (Throwable $exception) {
            throw AuthenticationUnavailable::create('Verification request controls are unavailable.', previous: $exception);
        }

        if ($result > 0) {
            throw VerificationLimited::create('The verification request limit was reached.', ['retryAfter' => $result]);
        }
    }
}
