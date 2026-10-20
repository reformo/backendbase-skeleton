import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createSessionClient } from '../../resources/guides/examples/identity-session-client.mjs';

const response = (token, refreshToken = 'refresh-next') => new Response(JSON.stringify({ session: {
    accessToken: token, refreshToken, expiresAt: '2026-10-20T10:15:00Z',
    tokenType: 'Bearer', sessionIdleExpiresAt: '2026-10-21T10:00:00Z', sessionExpiresAt: '2026-10-27T10:00:00Z',
} }), { status: 200 });
const setup = (send, options = {}) => createSessionClient({
    baseUrl: 'https://api.example.com/user-api/', fetch: send, now: () => Date.parse('2026-10-20T10:00:00Z'),
    withLock: async (_name, operation) => operation(), createOperationKey: () => 'refresh-operation', ...options,
});

test('parallel browser requests share one renewal and do not send a body refresh token', async () => {
    let renewals = 0;
    const client = setup(async (url, options) => {
        if (url.pathname.endsWith('/auth/refresh')) {
            renewals += 1;
            assert.equal(options.credentials, 'include');
            assert.deepEqual(JSON.parse(options.body), { refreshTokenTransport: 'cookie' });
            await new Promise((resolve) => setImmediate(resolve));
            return response('access');
        }
        assert.equal(options.headers.Authorization, 'Bearer access');
        return new Response('{}');
    });
    await Promise.all([client.request('identity'), client.request('identity')]);
    assert.equal(renewals, 1);
});

test('native renewal uses secure storage and saves the rotated token', async () => {
    let secret = 'refresh-old';
    const storage = { load: async () => secret, save: async (value) => { secret = value; }, clear: async () => { secret = null; } };
    const client = setup(async (_url, options) => {
        assert.equal(options.credentials, 'omit');
        assert.equal(JSON.parse(options.body).refreshToken, 'refresh-old');
        return response('access');
    }, { transport: 'body', storage });
    const session = await client.renew();
    assert.equal(secret, 'refresh-next');
    assert.equal(session.refreshToken, undefined);
});

test('an uncertain renewal reuses its operation key and body', async () => {
    const requests = [];
    let attempts = 0;
    const client = setup(async (_url, options) => {
        requests.push({ body: options.body, key: options.headers['Idempotency-Key'] });
        attempts += 1;
        if (attempts === 1) throw new TypeError('Connection lost after cookie delivery.');
        return response('access');
    });
    await assert.rejects(client.renew(), /Connection lost/);
    await client.renew();
    assert.deepEqual(requests[0], requests[1]);
});

test('secure storage failure retains the old refresh request until persistence succeeds', async () => {
    let writes = 0;
    const requests = [];
    const storage = {
        load: async () => 'old-secret', clear: async () => {},
        save: async () => { writes += 1; if (writes === 1) throw new Error('Storage unavailable'); },
    };
    const client = setup(async (_url, options) => { requests.push(options.body); return response('access'); }, { transport: 'body', storage });
    await assert.rejects(client.renew(), /Storage unavailable/);
    await client.renew();
    assert.equal(requests[0], requests[1]);
});

test('a protected write retries one authentication failure with the same key and body', async () => {
    const writes = [];
    let refreshes = 0;
    const client = setup(async (url, options) => {
        if (url.pathname.endsWith('/auth/refresh')) return response(`token-${++refreshes}`);
        writes.push(options);
        return new Response('{}', { status: writes.length === 1 ? 401 : 200 });
    });
    const result = await client.request('resource', { method: 'POST', json: { value: 'one' }, headers: { 'Idempotency-Key': 'original-write' } });
    assert.equal(result.status, 200);
    assert.equal(writes.length, 2);
    assert.equal(writes[0].body, writes[1].body);
    assert.equal(writes[0].headers['Idempotency-Key'], 'original-write');
    assert.equal(writes[1].headers['Idempotency-Key'], 'original-write');
});

test('a rejected refresh requires login and clears the native secret', async () => {
    let cleared = false;
    const storage = { load: async () => 'secret', save: async () => {}, clear: async () => { cleared = true; } };
    const client = setup(async () => new Response('{}', { status: 401 }), { transport: 'body', storage });
    await assert.rejects(client.request('identity'), /Sign in again/);
    assert.equal(cleared, true);
});

test('an uncertain application write is not repeated automatically', async () => {
    let writes = 0;
    const client = setup(async (url) => {
        if (url.pathname.endsWith('/auth/refresh')) return response('access');
        writes += 1;
        throw new TypeError('Connection lost');
    });
    await assert.rejects(client.request('resource', { method: 'POST', json: {}, headers: { 'Idempotency-Key': 'one-write' } }), /Connection lost/);
    assert.equal(writes, 1);
});

test('a fresh access token sends requests without renewal or a timer', async () => {
    const paths = [];
    const client = setup(async (url) => { paths.push(url.pathname); return response('access'); });
    await client.login({ identifier: 'ada@example.com', password: 'example-password' }, 'login-operation');
    await client.request('identity');
    assert.deepEqual(paths, ['/user-api/auth/login', '/user-api/identity']);
    await assert.rejects(client.request('resource', { method: 'POST', json: {} }), /Idempotency-Key/);
});


test('superseded renewal replay retries once with the current browser cookie', async () => {
    const keys = [];
    let sequence = 0;
    const client = setup(async (_url, options) => {
        keys.push(options.headers['Idempotency-Key']);
        if (keys.length === 1) return new Response(JSON.stringify({ code: 'identity/conflict' }), { status: 409 });
        return response('current-access');
    }, { createOperationKey: () => `refresh-${++sequence}` });
    await client.renew();
    assert.deepEqual(keys, ['refresh-1', 'refresh-2']);
});

test('the client cannot send access tokens outside its API base URL', async () => {
    const client = setup(async () => response('access'));
    await client.login({ identifier: 'ada@example.com', password: 'example-password' }, 'one-login');
    await assert.rejects(client.request('https://attacker.example.net/collect'), /API base URL/);
    await assert.rejects(client.request('../other-api/identity'), /API base URL/);
});

test('browser clients share the host lock across UserApi and ExampleApi', async () => {
    const names = [];
    const withLock = async (name, operation) => { names.push(name); return operation(); };
    const send = async () => response('access');
    const user = setup(send, { withLock });
    const example = setup(send, { withLock, baseUrl: 'https://api.example.com/example-api/' });
    await Promise.all([user.renew(), example.renew()]);
    assert.deepEqual(names, ['backendbase-session:api.example.com', 'backendbase-session:api.example.com']);
});

test('native clients serialize shared storage across API surfaces', async () => {
    let secret = 'initial-secret';
    const received = [];
    const storage = { load: async () => secret, save: async (value) => { secret = value; }, clear: async () => {} };
    const send = async (_url, options) => {
        const provided = JSON.parse(options.body).refreshToken;
        await new Promise((resolve) => setImmediate(resolve));
        assert.equal(provided, secret);
        received.push(provided);
        return response('access', `secret-${received.length}`);
    };
    const user = setup(send, { storage, transport: 'body' });
    const example = setup(send, { storage, transport: 'body', baseUrl: 'https://api.example.com/example-api/' });
    await Promise.all([user.renew(), example.renew()]);
    assert.deepEqual(received, ['initial-secret', 'secret-1']);
});
