# Architecture Rules

- Domain code owns aggregates, invariants, value objects, and domain events.
- Domain code must not depend on HTTP, Doctrine, queues, containers, application handlers, or infrastructure.
- Contracts define commands, queries, ports, read models, and versioned event schemas.
- Application handlers orchestrate use cases through domain objects and ports.
- Application handlers must not parse HTTP, run SQL, publish directly to brokers, or build HTTP responses.
- Infrastructure adapters map project contracts to frameworks, databases, brokers, and vendors.
- Infrastructure adapters must not make business decisions.
- HTTP adapters belong under `Infrastructure/UseCase`, outside bounded contexts.
- Shared code contains stable cross-module building blocks.
- Shared code must not depend on `Domain` or `Infrastructure`.
- One bounded context must not import another context or its adapters.
- Depend on project-owned interfaces at technology boundaries.

Architecture tests under `tests/Architecture` enforce domain purity, context isolation, adapter direction, framework-import rules, and the Shared boundary.

Basis: `resources/docs/0-project.html`, `resources/docs/1-bounded-contexts.html`, `resources/docs/10-testing-and-quality.html`.
