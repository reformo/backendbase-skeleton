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
    const httpRoot = '../../src/Backendbase/Infrastructure/Adapters/Http/Idempotency/';
    const storeFile = '../../src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/Idempotency/DoctrineRequestReplayStore.php';
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

    mountFlow('execution-flow', [
        { kind: '01 · HTTP boundary', title: 'Validate the request', summary: 'Current access + key + bounded body', detail: 'Protected authentication runs before replay. The middleware requires one valid key and checks the raw body limit before claiming the operation.', file: `${httpRoot}Idempotency.php` },
        { kind: '02 · Durable claim', title: 'Reserve the operation', summary: 'Commit a unique claim before execution', detail: 'The scope contains operation ID, caller identity, and key. The Doctrine store rejects an active application transaction. A unique claim prevents another matching attempt from executing.', file: storeFile },
        { kind: '03 · Application', title: 'Execute the operation', summary: 'Keep existing application transactions', detail: 'The handler runs after claim creation. Exceptions become safe HTTP error responses. Business transactions, Redis operations, and external effects retain their existing boundaries.', file: `${httpRoot}ReplayExecution.php` },
        { kind: '04 · Completion', title: 'Store the response', summary: 'Encrypt, store, then return', detail: 'Store the encrypted status, body, and selected headers. Completion starts the 24-hour retention period. Failure after an effect can leave an unresolved claim.', file: `${httpRoot}ReplayExchange.php`, result: true },
    ], ['accepted request', 'claim committed', 'response produced']);

    mountFlow('replay-flow', [
        { kind: '01 · Current access', title: 'Authenticate protected retries', summary: 'Revoked or expired access cannot replay', detail: 'Realm selection and protected authentication run before the replay middleware. Successful logout can therefore make the same-token retry return 401.', file: '../../src/Backendbase/Infrastructure/Inbound/UserApi/Controllers/Identity/ModuleConfig.php' },
        { kind: '02 · Scoped lookup', title: 'Find the retained claim', summary: 'Operation ID + caller identity + key', detail: 'An unexpired completed claim can supply a response. An expired completed claim is removed and can permit another execution. Unresolved claims never expire automatically.', file: storeFile },
        { kind: '03 · Request matching', title: 'Compare and decrypt', summary: 'Exact request content must match', detail: 'A different fingerprint returns 422. A matching unresolved claim returns 409. A completed matching response is decrypted. Storage or decryption failures return 503.', file: `${httpRoot}ReplayExchange.php` },
        { kind: '04 · Stored result', title: 'Return the original result', summary: 'Status + body + selected headers', detail: 'The handler does not execute again. Errors can replay too. Reading the result does not renew retention, token expiry, or contact verification time.', file: `${httpRoot}ReplayResponse.php`, result: true },
    ], ['access accepted', 'claim found', 'completed match']);

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
