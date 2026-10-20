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

    mountFlow("read-flow", [
    {
        "kind": "01 \u00b7 Contract",
        "title": "Construct the query",
        "summary": "GetEntryByCriteria",
        "detail": "Validate boundary input. Select type, nullable target, group, and key. This query declares EntryDetails|null and does not mutate state.",
        "file": "../../src/Backendbase/Domain/ExampleCatalog/Contracts/Query/GetEntryByCriteria.php",
        "result": false
    },
    {
        "kind": "02 \u00b7 Dispatch",
        "title": "Resolve the handler",
        "summary": "QueryBus + handler registry",
        "detail": "QueryBus::handle() resolves the concrete query class through RegistryHandlerResolver, obtains its handler from the container, and calls handle().",
        "file": "../../src/Backendbase/Infrastructure/Adapters/CQRS/ContainerAwareQueryBus.php",
        "result": false
    },
    {
        "kind": "03 \u00b7 Read adapter",
        "title": "Select the projection",
        "summary": "EntryReadRepository + DBAL",
        "detail": "The handler uses a read port. The Doctrine adapter binds criteria, excludes removed rows, and validates persisted values through EntryReadModelMapper.",
        "file": "../../src/Backendbase/Domain/ExampleCatalog/Adapters/Persistence/Doctrine/EntryReadRepository.php",
        "result": false
    },
    {
        "kind": "04 \u00b7 Result",
        "title": "Return details or null",
        "summary": "No aggregate mutation or event",
        "detail": "The query returns Contracts/ReadModel/EntryDetails or null. The inbound adapter chooses transport keys and missing-result behavior.",
        "file": "../../src/Backendbase/Domain/ExampleCatalog/Application/QueryHandlers/GetEntryByCriteriaHandler.php",
        "result": true
    }
], ["dispatch", "call read port", "return projection"]);

    mountFlow("write-flow", [
    {
        "kind": "01 \u00b7 Application",
        "title": "Open the transaction",
        "summary": "AddEntryHandler \u2192 EntryEventTransaction",
        "detail": "The handler passes its work and optional domain-event factory to EntryEventTransaction. The shared Doctrine wrapper begins the transaction before the work runs.",
        "file": "../../src/Backendbase/Domain/ExampleCatalog/Application/CommandHandlers/AddEntryHandler.php",
        "result": false
    },
    {
        "kind": "02 \u00b7 Domain and storage",
        "title": "Create and persist Entry",
        "summary": "Clock \u2192 aggregate \u2192 write port",
        "detail": "EntryWrites reads the clock inside the transaction, creates the aggregate, persists it, and returns the integration event. Repository flushes remain inside the outer transaction.",
        "file": "../../src/Backendbase/Domain/ExampleCatalog/Application/EntryWrites.php",
        "result": false
    },
    {
        "kind": "03 \u00b7 Before commit",
        "title": "Publish local reactions",
        "summary": "Domain listener \u2192 integration subscribers",
        "detail": "EntryEventTransaction publishes the domain event. The integration manager then runs local subscribers and appends one outbox row when the queue flag is true.",
        "file": "../../src/Backendbase/Domain/ExampleCatalog/Application/EntryEventTransaction.php",
        "result": false
    },
    {
        "kind": "04 \u00b7 Database result",
        "title": "Flush and commit",
        "summary": "Business + local writes + outbox",
        "detail": "The shared wrapper flushes and commits on one connection. Failure rolls back database work. Queue publication follows after commit.",
        "file": "../../src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineIntegrationEventTransaction.php",
        "result": true
    }
], ["transaction begins", "return event", "flush and commit"]);

    mountFlow("delivery-flow", [
    {
        "kind": "01 \u00b7 Committed outbox",
        "title": "Relay the event",
        "summary": "Claim \u2192 publish \u2192 record result",
        "detail": "OutboxRelayService coordinates publication of committed rows. A publish can succeed before the result is recorded, so duplicate delivery remains possible.",
        "file": "../../src/Backendbase/Infrastructure/Messaging/OutboxRelayService.php",
        "result": false
    },
    {
        "kind": "02 \u00b7 Queue boundary",
        "title": "Validate the delivery",
        "summary": "Identity + destination + version",
        "detail": "The processor validates metadata and replay eligibility. It adds _Event to the producer event name and calls the inbox transaction.",
        "file": "../../src/Backendbase/Infrastructure/Adapters/Queue/ExternalIntegrationEventMessageProcessor.php",
        "result": false
    },
    {
        "kind": "03 \u00b7 Separate transaction",
        "title": "Map and dispatch",
        "summary": "EntryAddedMessage \u2192 subscriber",
        "detail": "Inside the inbox transaction, the dispatcher resolves the versioned carrier and maps the payload. External dispatch calls registered queue subscribers without appending another outbox row.",
        "file": "../../src/Backendbase/Infrastructure/Adapters/Queue/ExternalIntegrationEventDispatcher.php",
        "result": false
    },
    {
        "kind": "04 \u00b7 Consumer result",
        "title": "Commit and acknowledge",
        "summary": "Subscriber work + processed_at",
        "detail": "Successful subscriber database writes and processed_at commit together. A processed duplicate skips dispatch. The current Entry subscriber only logs metadata.",
        "file": "../../src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/DoctrineInboxMessageTransaction.php",
        "result": true
    }
], ["broker delivery", "processOnce", "subscriber succeeds"]);

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
