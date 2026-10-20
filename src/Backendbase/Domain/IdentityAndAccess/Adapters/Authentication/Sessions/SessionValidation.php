<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Sessions;

final class SessionValidation
{
    public const string SCRIPT = SessionScripts::READ . "\n" . SessionScripts::LIVE . "\n" . <<<'LUA'
local token, session, blocked = record(KEYS[1]), record(KEYS[2]), redis.call('EXISTS', KEYS[3])
local state = decoded(session)
if state and state.sessionVersion ~= nil then
    local now = tonumber(redis.call('TIME')[1])
    local expected = decoded(ARGV[2])
    if blocked ~= 0 or token ~= ARGV[1] or not live(state, now) or not expected or not matches(state, expected) then return {} end
    if ARGV[3] == '1' then
        redis.call('JSON.SET', KEYS[2], '$.lastUsedAt', tostring(now))
        redis.call('EXPIREAT', KEYS[2], math.min(state.maximumExpiresAt, now + state.policy.idleTimeoutSeconds))
    end
    session = ARGV[2]
end
return {token, session, blocked}
LUA;
}
