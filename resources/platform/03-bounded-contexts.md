# Bounded Contexts

A bounded context owns one business language. Create it under `src/Backendbase/Domain/{PascalCaseContextName}`.

Name each context after its business capability. Omit `Context` and `BoundedContext` suffixes. `ExampleCatalog` is this repository's demonstration context. Reuse its structure, but choose the target project's business names. Name its records and operations with `Entry`, such as `EntryIdentity`, `AddEntry`, and `EntryAdded`.

## Module shape

- `Domain`: aggregates, value objects, enums, and rules.
- `Contracts`: commands, queries, ports, read models, and event contracts.
- `Application`: handlers, domain listeners, and integration subscribers.
- `Adapters/Persistence/Doctrine`: production read and write adapters.
- `Adapters/Persistence/Memory`: fast test adapters.
- `Tests`: tests owned by the movable module.
- `ServiceProvider.php`: port bindings and subscriber metadata.

Boot discovers only `src/Backendbase/Domain/*/ServiceProvider.php`. A normal context needs no Composer mapping change.

Use `ExampleCatalog` as the reference. No `Content` context currently exists. `IdentityAndAccess` also owns its port bindings through this provider pattern.

Keep module-owned tests inside the module. Keep architecture, shared, infrastructure, and API tests under root `tests`.

Communicate across context boundaries with stable contracts. Prefer versioned integration events for asynchronous communication.

Basis: `resources/docs/1-bounded-contexts.html`.
