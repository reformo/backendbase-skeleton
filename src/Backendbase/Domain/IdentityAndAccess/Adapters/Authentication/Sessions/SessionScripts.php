<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Sessions;

final class SessionScripts
{
    public const string READ = <<<'LUA'
local function record(key)
    if redis.call('TYPE', key).ok == 'string' then return redis.call('GET', key) end
    return redis.call('JSON.GET', key, '.')
end
local function decoded(value)
    if not value then return nil end
    local ok, state = pcall(cjson.decode, value)
    if ok and type(state) == 'table' then return state end
    return nil
end
LUA;

    public const string LIVE = <<<'LUA'
local function integer(value)
    return type(value) == 'number' and value % 1 == 0
end
local function live(state, now)
    if not state or state.sessionVersion ~= 2 or type(state.policy) ~= 'table' then return false end
    local policy = state.policy
    if not integer(policy.accessTokenLifetimeSeconds) or policy.accessTokenLifetimeSeconds < 60 or policy.accessTokenLifetimeSeconds > 3600 then return false end
    if not integer(policy.idleTimeoutSeconds) or not integer(policy.maximumLifetimeSeconds) or policy.idleTimeoutSeconds < 60 or policy.maximumLifetimeSeconds < policy.idleTimeoutSeconds or policy.maximumLifetimeSeconds > 31536000 then return false end
    if not integer(state.createdAt) or not integer(state.lastUsedAt) or not integer(state.maximumExpiresAt) then return false end
    if state.createdAt < 1 or state.maximumExpiresAt ~= state.createdAt + policy.maximumLifetimeSeconds or state.lastUsedAt < state.createdAt then return false end
    if type(state.refreshHash) ~= 'string' or #state.refreshHash ~= 64 or not string.match(state.refreshHash, '^[0-9a-f]+$') then return false end
    return now >= state.lastUsedAt and now < math.min(state.maximumExpiresAt, state.lastUsedAt + policy.idleTimeoutSeconds)
end
local function matches(state, expected)
    for _, name in ipairs({'accountId', 'sub', 'sid', 'authVersion', 'realm', 'actorType'}) do
        if state[name] ~= expected[name] then return false end
    end
    return true
end
LUA;
}
