<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Sessions;

final class SessionRenewal
{
    public const string LOOKUP = SessionScripts::READ . "\n" . SessionScripts::LIVE . "\n" . <<<'LUA'
local value = record(KEYS[1])
local state = decoded(value)
local now = tonumber(redis.call('TIME')[1])
if redis.call('EXISTS', KEYS[3]) ~= 0 or not live(state, now) then return 0 end
if state.refreshHash == ARGV[1] then return value end
if redis.call('EXISTS', KEYS[2]) == 1 then
    if ARGV[2] == '1' then return value end
    redis.call('DEL', KEYS[1])
end
return 0
LUA;

    public const string ROTATE = SessionScripts::READ . "\n" . SessionScripts::LIVE . "\n" . <<<'LUA'
local state = decoded(record(KEYS[2]))
local now = tonumber(redis.call('TIME')[1])
local expires = tonumber(ARGV[4])
if redis.call('EXISTS', KEYS[3]) ~= 0 or not live(state, now) then return 0 end
if state.refreshHash ~= ARGV[1] then
    if redis.call('EXISTS', KEYS[4]) == 1 then redis.call('DEL', KEYS[2]) end
    return 0
end
local replacement = decoded(ARGV[3])
local token = decoded(ARGV[2])
if not live(replacement, now) or not token or not matches(state, replacement) or not matches(replacement, token) or replacement.jti ~= token.jti then return 0 end
if replacement.createdAt ~= state.createdAt or replacement.maximumExpiresAt ~= state.maximumExpiresAt or expires <= now or expires > state.maximumExpiresAt or redis.call('EXISTS', KEYS[1]) ~= 0 then return 0 end
for _, name in ipairs({'accessTokenLifetimeSeconds', 'idleTimeoutSeconds', 'maximumLifetimeSeconds'}) do
    if state.policy[name] ~= replacement.policy[name] then return 0 end
end
local function write(command, ...)
    local result = redis.pcall(command, ...)
    if type(result) == 'table' and result.err then
        redis.call('DEL', KEYS[1], KEYS[2], KEYS[4])
        error(result.err)
    end
end
replacement.lastUsedAt = now
write('JSON.SET', KEYS[1], '$', ARGV[2], 'NX')
write('EXPIRE', KEYS[1], expires - now)
write('EXPIREAT', KEYS[1], expires)
write('SET', KEYS[4], '1', 'EXAT', state.maximumExpiresAt)
write('JSON.SET', KEYS[2], '$', cjson.encode(replacement))
write('EXPIREAT', KEYS[2], math.min(state.maximumExpiresAt, now + state.policy.idleTimeoutSeconds))
return 1
LUA;
}
