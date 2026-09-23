# Architecture Rules

- Domain code owns aggregates, invariants, value objects, and domain events.
- Domain code must not depend on HTTP, Doctrine, queues, containers, application handlers, or infrastructure.
- Contracts define commands, queries, ports, read models, and versioned event schemas.
- Application handlers orchestrate use cases through domain objects and ports.
- Application handlers must not parse HTTP, run SQL, publish directly to brokers, or build HTTP responses.
- Application services must not depend on `Infrastructure`.
- Infrastructure adapters map project contracts to frameworks, databases, brokers, and vendors.
- Infrastructure adapters must not make business decisions.
- HTTP adapters belong under `Infrastructure/UseCase`, outside bounded contexts.
- Shared code contains stable cross-module contracts and values. Framework support belongs in Infrastructure.
- Shared code must not depend on `Application`, `Domain`, or `Infrastructure`.
- No Shared code may import framework namespaces.
- One bounded context must not import another context or its adapters.
- Depend on project-owned interfaces at technology boundaries.
- Inbound adapters must not depend on outbound adapter implementations. Outbound adapters must not depend on inbound adapter implementations.
- Every command and query must map to a resolvable handler in its context registry. Contracts must not import handlers.
- Domain-listener attributes must have one positional target in the same context. The target must implement the correct interface and resolve from the production container.

Architecture tests under `tests/Architecture` enforce domain purity, context isolation, Application direction, both adapter directions, Shared framework isolation, registry mappings, domain-listener targets, and the Shared boundary.

Basis: `resources/docs/0-project.html`, `resources/docs/1-bounded-contexts.html`, `resources/docs/10-testing-and-quality.html`.
