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
    const contextRoot = '../../src/Backendbase/Domain/ExampleCatalog/';
    const infrastructureRoot = '../../src/Backendbase/Infrastructure/';
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

    mountFlow('write-flow', [
        { kind: '01 · Application entry', title: 'Enter the transaction', summary: 'NewExample → AddEntryHandler', detail: 'The HTTP action constructs AddEntry. Its handler calls EntryEventTransaction, which delegates to IntegrationEventTransaction before business work starts.', file: `${contextRoot}Application/CommandHandlers/AddEntryHandler.php` },
        { kind: '02 · Business write', title: 'Create and persist Entry', summary: 'EntryWrites + write repository + Clock', detail: 'EntryWrites reads the clock inside the transaction, creates the aggregate, and persists it through the write port. The repository flush remains inside the active transaction.', file: `${contextRoot}Application/EntryWrites.php` },
        { kind: '03 · Before commit', title: 'Dispatch and append', summary: 'Local subscribers → one outbox row', detail: 'EntryEventTransaction first publishes its optional domain event. The integration event manager then runs local integration subscribers. DELIVER_VIA_QUEUE=true adds one outbox row on the same connection.', file: `${infrastructureRoot}Adapters/EventManager/ContainerAwareEventManager.php` },
        { kind: '04 · Database result', title: 'Commit the transaction', summary: 'Business + subscriber writes + outbox', detail: 'The transaction wrapper flushes and commits together. A failure rolls back database writes. The HTTP action can return 204 and the pre-generated identifier. Queue delivery follows later.', file: `${infrastructureRoot}Adapters/Persistence/Doctrine/DoctrineIntegrationEventTransaction.php`, result: true },
    ], ['begin transaction', 'return event', 'flush and commit']);

    mountFlow('delivery-flow', [
        { kind: '01 · Committed event', title: 'Relay the outbox row', summary: 'Claim → publish → mark published', detail: 'OutboxRelayService coordinates publication through OutboxPublication and the selected queue adapter. Publication can succeed before success is recorded, so redelivery remains possible.', file: `${infrastructureRoot}Messaging/OutboxRelayService.php` },
        { kind: '02 · Queue boundary', title: 'Validate delivery metadata', summary: 'Message ID + consumer + name + version', detail: 'ExternalIntegrationEventMessageProcessor validates the metadata and replay window. The external event name adds _Event to the producer name.', file: `${infrastructureRoot}Adapters/Queue/ExternalIntegrationEventMessageProcessor.php` },
        { kind: '03 · Inbox transaction', title: 'Map the registered carrier', summary: 'processOnce → EntryAddedMessage', detail: 'The inbox opens a separate transaction. ExternalIntegrationEventDispatcher resolves the event name and version through the registered carrier before subscriber dispatch.', file: `${infrastructureRoot}Adapters/Queue/ExternalIntegrationEventDispatcher.php` },
        { kind: '04 · Consumer result', title: 'Handle and acknowledge', summary: 'Subscriber work + processed_at commit', detail: 'EntryAddedExternalSubscriber currently logs metadata only. Successful consumer database work and processed_at commit together. A duplicate does not dispatch again. External dispatch does not republish the received event.', file: `${infrastructureRoot}Adapters/Persistence/Doctrine/DoctrineInboxMessageTransaction.php`, result: true },
    ], ['broker delivery', 'processOnce', 'subscriber succeeds']);

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
