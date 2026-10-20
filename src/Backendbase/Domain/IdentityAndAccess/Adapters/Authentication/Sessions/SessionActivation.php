<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Sessions;

final class SessionActivation
{
    public const string SCRIPT = SessionScripts::READ . "\n" . SessionScripts::LIVE . "\n" . <<<'LUA'
local now = tonumber(redis.call('TIME')[1])
local state = decoded(ARGV[2])
local token = decoded(ARGV[1])
local expires = tonumber(ARGV[3])
if not live(state, now) or not token or not matches(state, token) or state.jti ~= token.jti or expires <= now or expires > state.maximumExpiresAt then return 0 end
if redis.call('EXISTS', KEYS[3]) == 1 or redis.call('EXISTS', KEYS[1]) == 1 or redis.call('EXISTS', KEYS[2]) == 1 then return 0 end
local function write(command, ...)
    local result = redis.pcall(command, ...)
    if type(result) == 'table' and result.err then
        redis.call('DEL', KEYS[1], KEYS[2])
        error(result.err)
    end
end
local deadlines = {expires, math.min(state.maximumExpiresAt, state.lastUsedAt + state.policy.idleTimeoutSeconds)}
for index = 1, 2 do
    write('JSON.SET', KEYS[index], '$', ARGV[index], 'NX')
    write('EXPIRE', KEYS[index], deadlines[index] - now)
    write('EXPIREAT', KEYS[index], deadlines[index])
end
return 1
LUA;
}
