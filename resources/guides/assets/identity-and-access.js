/* Documentation diagrams only. This file does not call an application API. */
(() => {
    'use strict';

    const runtime = window.GuideFlow;
    if (!runtime) {
        document.querySelectorAll('.flow-placeholder').forEach((element) => {
            element.textContent = 'The diagram runtime is unavailable. Read the flow steps below.';
        });
        return;
    }

    const { createElement: element, createRoot, useState, ReactFlow, Background, Controls, Handle, Position, MarkerType } = runtime;
    const contextRoot = '../../src/Backendbase/Domain/IdentityAndAccess/';
    const configurationRoot = '../../src/Backendbase/Infrastructure/Configuration/';
    const positions = [{ x: 0, y: 0 }, { x: 355, y: 0 }, { x: 355, y: 230 }, { x: 0, y: 230 }];

    function FlowStep({ data }) {
        return element('div', { className: `flow-node${data.result ? ' is-result' : ''}` },
            element('span', { className: 'node-kind' }, data.kind),
            element('button', { className: 'node-inspect nodrag nopan', type: 'button', onClick: data.inspect }, data.title),
            element('p', null, data.summary),
            element(Handle, { type: 'target', position: Position.Left, id: 'in-left' }),
            element(Handle, { type: 'target', position: Position.Right, id: 'in-right' }),
            element(Handle, { type: 'target', position: Position.Top, id: 'in-top' }),
            element(Handle, { type: 'source', position: Position.Right, id: 'out-right' }),
            element(Handle, { type: 'source', position: Position.Bottom, id: 'out-bottom' }),
            element(Handle, { type: 'source', position: Position.Left, id: 'out-left' }));
    }

    const nodeTypes = { step: FlowStep };

    function FlowDiagram({ steps, labels, wide }) {
        const [selectedIndex, selectStep] = useState(0);
        const selected = steps[selectedIndex];
        const nodes = steps.map((step, index) => ({
            id: `step-${index}`,
            type: 'step',
            position: positions[index],
            data: { ...step, inspect: () => selectStep(index) },
        }));
        const routes = [['out-right', 'in-left'], ['out-bottom', 'in-top'], ['out-left', 'in-right']];
        const edges = routes.map(([sourceHandle, targetHandle], index) => ({
            id: `transition-${index}`,
            source: `step-${index}`,
            target: `step-${index + 1}`,
            sourceHandle,
            targetHandle,
            type: 'smoothstep',
            label: labels[index],
            markerEnd: { type: MarkerType.ArrowClosed, color: '#607f6d', width: 17, height: 17 },
            style: { stroke: '#607f6d', strokeWidth: 1.5 },
            labelBgPadding: [6, 4],
            labelBgBorderRadius: 3,
        }));

        return element('div', null,
            element('div', { className: 'flow-canvas' },
                element(ReactFlow, {
                    nodes, edges, nodeTypes,
                    fitView: wide,
                    fitViewOptions: { padding: .15, maxZoom: 1 },
                    defaultViewport: { x: 25, y: 30, zoom: .9 },
                    minZoom: .25, maxZoom: 1.8,
                    nodesDraggable: false, nodesConnectable: false, nodesFocusable: false,
                    edgesFocusable: false, elementsSelectable: false,
                    onNodeClick: (_event, node) => node.data.inspect(),
                    zoomOnScroll: false, zoomOnDoubleClick: false,
                    preventScrolling: false,
                    colorMode: 'light',
                    ariaLabelConfig: { 'controls.fitView.ariaLabel': 'Fit the complete flow in the diagram' },
                },
                element(Background, { color: '#d6dfd5', gap: 20, size: 1 }),
                element(Controls, { showInteractive: false }))),
            element('div', { className: 'flow-detail', 'aria-live': 'polite', 'aria-atomic': 'true' },
                element('p', null, element('strong', null, selected.title), ' — ', selected.detail),
                element('p', { className: 'source' }, 'Source: ', element('a', { href: selected.file }, selected.file.replace('../../', '')))));
    }

    function mountFlow(id, steps, labels) {
        const host = document.getElementById(id);
        const root = createRoot(host);
        const wide = host.getBoundingClientRect().width >= 640;
        const render = (nextSteps) => root.render(element(FlowDiagram, { steps: nextSteps, labels, wide }));
        render(steps);
        return render;
    }

    mountFlow('configuration-flow', [
        { kind: '01 · Environment', title: 'Resolve deployment values', summary: 'Process environment + optional .env', detail: 'Dotenv preserves existing process values. backendbaseEnv reads $_ENV, then getenv(), then its code default. An empty value does not activate the default.', file: '../../src/Backendbase/Shared/Helpers/functions.php' },
        { kind: '02 · Configuration', title: 'Merge PHP providers', summary: 'Shared providers → selected API providers', detail: 'The HTTP bootstrap loads config/autoload/* before config/{api-slug}/*. A selected API can override a shared key. Keep registration policies coordinated across UserApi and ExampleApi.', file: '../../public/index.php' },
        { kind: '03 · Validation', title: 'Resolve typed settings', summary: 'RegistrationSettings · SessionSettings', detail: 'PHP-DI resolves settings when their consumers are needed. RegistrationSettings rejects unsupported boolean values. SessionSettings validates both realm duration policies. IdentitySettings validates keys, realm separation, and sender.', file: `${configurationRoot}Identity/RegistrationSettings.php` },
        { kind: '04 · Application', title: 'Apply the policy', summary: 'RegistrationPolicy + RealmSessionPolicies', detail: 'Container definitions supply typed domain policies. Application workflows use those policies. Existing Accounts retain stored registration acceptance.', file: '../../config/dependencies/application.php', result: true },
    ], ['environment helpers', 'merged settings', 'dependency injection']);

    const modes = {
        mobile: { mobile: true, email: false, title: 'Require mobile proof', summary: 'Mobile proof is required. Email proof is optional. A context token is required.' },
        email: { mobile: false, email: true, title: 'Require email proof', summary: 'Email proof is required. Mobile proof is optional. A context token is required.' },
        both: { mobile: true, email: true, title: 'Require both proofs', summary: 'Mobile and email proof are required. Both use the same context token.' },
        neither: { mobile: false, email: false, title: 'No required proof', summary: 'Neither proof is required. The context token can be omitted or null. Every supplied token is validated.' },
    };

    function registrationSteps(mode) {
        return [
            { kind: '01 · HTTP boundary', title: 'Validate registration input', summary: 'Email + mobile + password in every mode', detail: 'Names remain optional. Unknown fields and type coercion are rejected. Every supplied context token must be a canonical 43-character base64url value.', file: `${contextRoot}Contracts/Input/RegistrationInput.php` },
            { kind: '02 · Domain policy', title: mode.title, summary: mode.mobile || mode.email ? 'Acquire contact slots, then check freshness' : 'Accept omission; consume usable optional proofs', detail: `${mode.summary} With a context, acquire email then mobile before reading the authorization time. Evidence must match the submitted contacts and USER registration scope.`, file: `${contextRoot}Application/Registration/RegistrationEvidence.php` },
            { kind: '03 · Database transaction', title: 'Create Account and User', summary: 'Store credentials + consume selected proofs', detail: 'Password hashing occurs before the transaction. Uniqueness checks, identity creation, credential storage, and proof consumption share the identity transaction. Failure rolls them back.', file: `${contextRoot}Application/Registration/RegistrationCompletion.php` },
            { kind: '04 · Stored decision', title: 'Registration accepted', summary: 'Acceptance and ownership remain separate', detail: 'Store registrationAccepted=true. Store ownershipVerified=true only if registration consumes evidence. USER login uses acceptance; ADMIN login still requires ownership. Registration returns identifiers, not a session.', file: `${contextRoot}Domain/Identity/Account.php`, result: true },
        ];
    }

    const renderRegistration = mountFlow('registration-flow', registrationSteps(modes.mobile), ['typed input', 'all requirements pass', 'atomic commit']);

    document.querySelectorAll('input[name="registration-mode"]').forEach((input) => {
        input.addEventListener('change', () => {
            const mode = modes[input.value];
            document.getElementById('policy-summary').textContent = mode.summary;
            document.getElementById('policy-example').textContent =
                `BACKENDBASE_IDENTITY_REGISTRATION_REQUIRE_MOBILE_VERIFICATION=${mode.mobile}\n` +
                `BACKENDBASE_IDENTITY_REGISTRATION_REQUIRE_EMAIL_VERIFICATION=${mode.email}`;
            renderRegistration(registrationSteps(mode));
        });
    });

    mountFlow('contact-flow', [
        { kind: '01 · Request', title: 'Validate the contact scope', summary: 'Context + realm + purpose + contact', detail: 'Anonymous USER registration supplies the contact value. Authenticated routes derive the stored contact. ADMIN anonymous requests require credentials and a current invited actor.', file: '../../src/Backendbase/Infrastructure/Adapters/Http/Identity/ContactHttpInput.php' },
        { kind: '02 · Redis', title: 'Reserve the send budget', summary: 'Cooldown + contact budget + caller IP budget', detail: 'Atomic Redis scripts enforce a 60-second cooldown, five sends per contact per hour, and twenty sends per caller IP per hour. Limiter failure returns 503 before verification mutation.', file: `${contextRoot}Adapters/Authentication/RedisVerificationLimiter.php` },
        { kind: '03 · Database transaction', title: 'Commit a code generation', summary: 'Five-minute code + stable delivery identity', detail: 'Store the keyed code hash and generation identity with pending delivery state. Plaintext codes and raw context tokens are not stored in contact_verification.', file: `${contextRoot}Application/Contacts/ContactRequests.php` },
        { kind: '04 · Notification provider', title: 'Send email or SMS', summary: 'Synchronous delivery after database commit', detail: 'ContactNotification uses ReliableNotificationDelivery and the selected email or SMS provider. A timeout can leave an unknown outcome. An explicit resend creates a new generation after quota checks.', file: `${contextRoot}Adapters/Notification/ContactNotification.php`, result: true },
    ], ['validated request', 'quota reserved', 'after commit']);

    mountFlow('session-flow', [
        { kind: '01 · Login', title: 'Select the trusted realm', summary: 'Account email or mobile + password', detail: 'UserApi and ExampleApi use USER. SystemAdminApi uses ADMIN. Route policy determines the realm; a request-body field cannot change it.', file: `${contextRoot}Application/Workflows/LoginWorkflow.php` },
        { kind: '02 · Current identity', title: 'Check Account and actor', summary: 'Enabled Account + realm gate + actor approval', detail: 'USER requires registration acceptance. ADMIN requires ownership and a separately approved actor. Removed actors fail. Password verification uses the Account Argon2id hash.', file: `${contextRoot}Domain/Identity/Account.php` },
        { kind: '03 · Session issuance', title: 'Activate token and session', summary: 'Signed JWT + Redis jti and sid records', detail: 'USER tokens default to 15 minutes. ADMIN tokens default to 5 minutes. A version 2 session stores independent idle and maximum limits before delivery.', file: `${contextRoot}Application/SessionIssuance.php` },
        { kind: '04 · Protected request', title: 'Check access on every request', summary: 'JWT + both Redis records + current database state', detail: 'Valid signatures alone are insufficient. Missing allowlist entries or ineligible identity state reject access. Redis failure returns 503. Successful authentication extends idle after database checks. Access-token and maximum deadlines remain fixed.', file: `${contextRoot}Application/AuthenticationGate.php`, result: true },
    ], ['identifier lookup', 'password accepted', 'bearer request']);

    mountFlow('renewal-flow', [
        { kind: '01 · Browser or mobile', title: 'Renew before the next request', summary: 'Access token has 30 seconds or less remaining', detail: 'The shared client helper renews on demand. It serializes concurrent calls and browser tabs. No background timer keeps an inactive session alive.', file: 'examples/identity-session-client.mjs' },
        { kind: '02 · Authentication', title: 'Check the refresh credential', summary: 'Realm + idle + maximum + current identity', detail: 'Browser cookies require an exact configured Origin. Mobile apps send their secure-storage token. Authentication runs before idempotency replay. Revoked sessions cannot replay a result.', file: '../../src/Backendbase/Infrastructure/Adapters/Http/Identity/Sessions/RefreshAuthentication.php' },
        { kind: '03 · Atomic Redis rotation', title: 'Replace the credential once', summary: 'Fresh access jti + refresh hash; stable session sid', detail: 'Keep the stored policy, creation time, and maximum deadline. Extend idle within that deadline. Record the consumed digest until maximum expiry. Reuse for a new operation revokes the session.', file: `${contextRoot}Adapters/Authentication/Sessions/SessionRenewal.php` },
        { kind: '04 · Client', title: 'Save and continue the request', summary: 'HttpOnly browser cookie or mobile secure storage', detail: 'Store the replacement before using the access token. Retry uncertain renewal with the same key and body. A response guard prevents old replay from replacing a newer credential.', file: 'examples/identity-session-client.mjs', result: true },
    ], ['POST /auth/refresh', 'eligible session', 'fixed maximum']);

    const contentsLinks = [...document.querySelectorAll('.contents a[href^="#"]')];
    const sections = [...document.querySelectorAll('main section[id]')];
    function updateContents() {
        const precedingSections = sections.filter((section) => section.getBoundingClientRect().top <= 160);
        const current = precedingSections.pop() || sections[0];
        contentsLinks.forEach((link) => {
            if (link.hash === `#${current.id}`) {
                link.setAttribute('aria-current', 'location');
                return;
            }
            link.removeAttribute('aria-current');
        });
    }
    window.addEventListener('scroll', updateContents, { passive: true });
    window.addEventListener('resize', updateContents);
    window.addEventListener('load', updateContents);
    updateContents();

    window.addEventListener('beforeprint', () => {
        document.querySelectorAll('.flow-text').forEach((details) => {
            details.dataset.openBeforePrint = String(details.open);
            details.open = true;
        });
    });
    window.addEventListener('afterprint', () => {
        document.querySelectorAll('.flow-text').forEach((details) => {
            details.open = details.dataset.openBeforePrint === 'true';
        });
    });
})();
