// Keep access tokens in memory. Supply operating system secure storage for body transport.
const nativeRenewals = new Map();

export function createSessionClient({
    baseUrl,
    transport = 'cookie',
    storage,
    fetch: send = globalThis.fetch,
    now = Date.now,
    createOperationKey = () => globalThis.crypto.randomUUID(),
    withLock = browserLock,
    headers: defaultHeaders = {},
}) {
    if (!['cookie', 'body'].includes(transport)) throw new TypeError('Invalid refresh token transport.');
    if (transport === 'body' && (!storage?.load || !storage?.save || !storage?.clear)) {
        throw new TypeError('Native clients require load, save, and clear secure storage functions.');
    }
    const root = new URL(baseUrl.endsWith('/') ? baseUrl : `${baseUrl}/`);
    const lockName = `backendbase-session:${root.hostname}`;
    const state = { session: null, refreshInFlight: null, pendingRefresh: null };
    const lock = (operation) => transport === 'cookie' ? withLock(lockName, operation) : nativeLock(lockName, operation);

    function endpoint(path) {
        const url = new URL(path.replace(/^\//, ''), root);
        if (url.origin !== root.origin || !url.pathname.startsWith(root.pathname)) {
            throw new TypeError('The request must use this API base URL.');
        }
        return url;
    }

    async function clear() {
        state.session = null;
        state.pendingRefresh = null;
        if (transport === 'body') await storage.clear();
    }

    async function accept(response) {
        if (response.status === 401) {
            await clear();
            throw new Error('The session ended. Sign in again.');
        }
        if (!response.ok) throw new Error(`Session operation failed (${response.status}).`);
        const { session } = await response.json();
        if (!session?.accessToken || !Number.isFinite(Date.parse(session.expiresAt))) {
            throw new TypeError('The session response is invalid.');
        }
        if (transport === 'body') {
            if (!session.refreshToken) throw new TypeError('The refresh token is missing.');
            await storage.save(session.refreshToken);
        }
        const { refreshToken, ...publicSession } = session;
        state.session = publicSession;
        return publicSession;
    }

    function sessionRequest(path, payload, operationKey) {
        return send(endpoint(path), {
            method: 'POST',
            credentials: transport === 'cookie' ? 'include' : 'omit',
            headers: { ...defaultHeaders, 'Content-Type': 'application/json', 'Idempotency-Key': operationKey },
            body: JSON.stringify(payload),
        });
    }

    async function renewOperation(recoverSuperseded = true) {
        if (!state.pendingRefresh) {
            const payload = { refreshTokenTransport: transport };
            if (transport === 'body') {
                payload.refreshToken = await storage.load();
                if (!payload.refreshToken) throw new Error('Sign in before you renew the session.');
            }
            state.pendingRefresh = { payload, operationKey: createOperationKey() };
        }
        const pending = state.pendingRefresh;
        // Retain this key and body after uncertain network, response, or secure storage failures.
        const response = await sessionRequest('auth/refresh', pending.payload, pending.operationKey);
        if (response.status === 409 && recoverSuperseded) {
            const problem = await response.clone().json();
            if (problem.code === 'identity/conflict') {
                state.pendingRefresh = null;
                return renewOperation(false);
            }
        }
        const session = await accept(response);
        state.pendingRefresh = null;
        return session;
    }

    function renew() {
        if (!state.refreshInFlight) {
            state.refreshInFlight = lock(() => renewOperation()).finally(() => { state.refreshInFlight = null; });
        }
        return state.refreshInFlight;
    }

    async function login(credentials, operationKey) {
        requireOperationKey(operationKey);
        return lock(async () => {
            const response = await sessionRequest('auth/login', { ...credentials, refreshTokenTransport: transport }, operationKey);
            const session = await accept(response);
            state.pendingRefresh = null;
            return session;
        });
    }

    async function request(path, { method = 'GET', json, headers = {} } = {}) {
        method = method.toUpperCase();
        if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) requireOperationKey(new Headers(headers).get('Idempotency-Key'));
        const body = json === undefined ? undefined : JSON.stringify(json);
        if (state.pendingRefresh || !state.session || Date.parse(state.session.expiresAt) - now() <= 30000) await renew();
        const accessToken = state.session.accessToken;
        const options = {
            method,
            credentials: transport === 'cookie' ? 'include' : 'omit',
            headers: { ...defaultHeaders, ...headers, Authorization: `Bearer ${accessToken}` },
            body,
        };
        if (body !== undefined) options.headers['Content-Type'] = 'application/json';
        const url = endpoint(path);
        const response = await send(url, options);
        if (response.status !== 401) return response;
        // Protected routes authenticate before execution. Retry once with the unchanged operation key and body.
        if (state.session?.accessToken === accessToken) await renew();
        options.headers.Authorization = `Bearer ${state.session.accessToken}`;
        return send(url, options);
    }

    async function logout(operationKey) {
        requireOperationKey(operationKey);
        const response = await request('identity/session', { method: 'DELETE', headers: { 'Idempotency-Key': operationKey } });
        if (response.ok) await clear();
        return response;
    }

    return { login, renew, request, logout };
}

function requireOperationKey(value) {
    if (typeof value !== 'string' || !/^[A-Za-z0-9][A-Za-z0-9._:-]{0,254}$/.test(value)) {
        throw new TypeError('Supply an Idempotency-Key for this operation. Keep the same key for retries.');
    }
}

function browserLock(name, operation) {
    if (!globalThis.navigator?.locks) {
        throw new Error('Cookie clients require Web Locks to serialize renewal across browser tabs.');
    }
    return globalThis.navigator.locks.request(name, operation);
}

function nativeLock(name, operation) {
    const previous = nativeRenewals.get(name) ?? Promise.resolve();
    // A prior failure remains with its caller. The next independent operation can proceed.
    const next = previous.then(operation, operation);
    const settled = next.then(() => undefined, () => undefined);
    nativeRenewals.set(name, settled);
    settled.then(() => {
        if (nativeRenewals.get(name) === settled) nativeRenewals.delete(name);
    });
    return next;
}
