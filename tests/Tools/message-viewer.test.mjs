import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createContext, runInContext } from 'node:vm';

const source = readFileSync(new URL('../../resources/dev/message-viewer/viewer.js', import.meta.url), 'utf8');
const flush = () => new Promise((resolve) => setImmediate(resolve));
const sms = (message) => ({ recipient: '+905551112233', message, timestamp: '2026-11-02T10:00:00Z' });
const email = () => ({ id: 'mail-1', subject: 'Example', sender: 'a@example.com', to: ['b@example.com'], cc: [], bcc: [], text: 'Text', timestamp: '2026-11-02T10:00:00Z' });

function element() {
    const attributes = new Map();
    return {
        textContent: '', className: '', hidden: false, disabled: false, value: '', placeholder: '', dataset: {}, children: [],
        addEventListener() {},
        setAttribute(name, value) { attributes.set(name, value); },
        hasAttribute(name) { return attributes.has(name); },
        querySelectorAll() { return []; },
        append(...children) { this.children.push(...children); },
        replaceChildren(...children) { this.children = children; },
    };
}

function viewer() {
    const elements = new Map();
    const requests = [];
    const timeouts = [];
    const intervals = [];
    const document = {
        querySelector(selector) {
            if (!elements.has(selector)) elements.set(selector, element());
            return elements.get(selector);
        },
        createElement: () => element(),
    };
    const context = createContext({
        document,
        fetch(path, options) {
            return new Promise((resolve, reject) => { requests.push({ path, options, resolve, reject }); });
        },
        AbortSignal: { timeout(milliseconds) { timeouts.push(milliseconds); return { milliseconds }; } },
        setInterval(callback, milliseconds) { intervals.push({ callback, milliseconds }); },
        Date,
        encodeURIComponent,
        Set,
    });
    runInContext(source, context, { filename: 'viewer.js' });
    return { context, document, elements, requests, timeouts, intervals, run: (code) => runInContext(code, context) };
}

function respond(request, payload, ok = true) {
    request.resolve({ ok, json: async () => payload });
}

test('refresh prevents overlap, retains data on failure, and recovers', async () => {
    const current = viewer();
    assert.equal(current.requests.length, 1);
    assert.equal(current.run('states.sms.loading'), true);
    await current.run('loadMessages()');
    assert.equal(current.requests.length, 1);
    assert.equal(current.intervals[0].milliseconds, 5000);
    assert.deepEqual(current.timeouts, [7000]);
    respond(current.requests[0], { messages: [sms('first')] });
    await flush();
    assert.equal(current.run('states.sms.messages[0].message'), 'first');
    assert.equal(current.run('states.sms.loading'), false);

    const failed = current.run('loadMessages()');
    current.requests[1].reject(new Error('Connection lost'));
    await failed;
    assert.equal(current.run('states.sms.messages[0].message'), 'first');
    assert.equal(current.run('states.sms.error'), 'Connection lost');
    const recovered = current.run('loadMessages()');
    respond(current.requests[2], { messages: [sms('second')] });
    await recovered;
    assert.equal(current.run('states.sms.messages[0].message'), 'second');
    assert.equal(current.run('states.sms.error'), null);
    assert.equal(current.elements.get('#refresh').disabled, false);
    assert.deepEqual(current.timeouts, [7000, 7000, 7000]);
});

test('switching types keeps pending responses in their own state', async () => {
    const current = viewer();
    current.run('showMessages("emails")');
    assert.deepEqual(current.requests.map((request) => request.path), ['/messages', '/emails']);
    respond(current.requests[0], { messages: [sms('waiting')] });
    await flush();
    assert.equal(current.run('activeType'), 'emails');
    assert.equal(current.run('states.sms.messages[0].message'), 'waiting');
    assert.equal(current.elements.get('#count').textContent, '0 of 0');
    respond(current.requests[1], { emails: [email()] });
    await flush();
    assert.equal(current.run('states.emails.messages[0].id'), 'mail-1');
    assert.equal(current.elements.get('#count').textContent, '1 of 1');
});

test('the refresh timer loads the selected message type', async () => {
    const current = viewer();
    respond(current.requests[0], { messages: [] });
    await flush();
    current.run('showMessages("emails")');
    respond(current.requests[1], { emails: [] });
    await flush();
    const scheduled = current.intervals[0].callback();
    assert.equal(current.requests[2].path, '/emails');
    respond(current.requests[2], { emails: [email()] });
    await scheduled;
    assert.equal(current.run('states.emails.messages[0].id'), 'mail-1');
});

test('a malformed successful payload records the error after replacing state', async () => {
    const current = viewer();
    respond(current.requests[0], { messages: [sms('previous')] });
    await flush();
    const pending = current.run('loadMessages()');
    respond(current.requests[1], { messages: null });
    await pending;
    assert.equal(current.run('states.sms.messages'), null);
    assert.equal(current.run('states.sms.loading'), false);
    assert.match(current.run('states.sms.error'), /filter|length/);
});

test('a status-render failure before the request leaves loading set', async () => {
    const current = viewer();
    respond(current.requests[0], { messages: [] });
    await flush();
    Object.defineProperty(current.elements.get('#refresh'), 'disabled', {
        configurable: true,
        set() { throw new Error('Status rendering failed'); },
    });
    await assert.rejects(current.run('loadMessages()'), /Status rendering failed/);
    assert.equal(current.run('states.sms.loading'), true);
    await current.run('loadMessages()');
    assert.equal(current.requests.length, 1);
});

test('a message-render failure keeps the assigned data and records the error', async () => {
    const current = viewer();
    respond(current.requests[0], { messages: [sms('previous')] });
    await flush();
    current.document.createElement = () => { throw new Error('Message rendering failed'); };
    const pending = current.run('loadMessages()');
    respond(current.requests[1], { messages: [sms('assigned')] });
    await pending;
    assert.equal(current.run('states.sms.messages[0].message'), 'assigned');
    assert.equal(current.run('states.sms.error'), 'Message rendering failed');
    assert.equal(current.run('states.sms.loading'), false);
});

test('a timeout failure clears loading and leaves the prior data available', async () => {
    const current = viewer();
    current.requests[0].reject(new Error('The request timed out'));
    await flush();
    assert.equal(current.run('states.sms.loading'), false);
    assert.equal(current.run('states.sms.messages.length'), 0);
    assert.equal(current.run('states.sms.error'), 'The request timed out');
    assert.deepEqual(current.timeouts, [7000]);
});
