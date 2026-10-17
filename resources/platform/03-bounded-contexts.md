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
- `ServiceProvider.php`: port bindings, command and query handler mappings, and subscriber metadata.

Boot discovers `src/Backendbase/Domain/*/ServiceProvider.php` for ports and handler mappings. A normal context needs no Composer mapping change.

`IdentityAndAccess` owns global Accounts, User and SystemAdmin actors, contact verification evidence, password credentials, and realm sessions.
Use `ExampleCatalog` as the business-module reference. No `Content` context currently exists.

ExampleCatalog Entry creation, change, and removal require explicit DateTimeImmutable instants.
EntryWrites reads the injected UTC clock during transactional work. Reconstitution preserves stored times.
EntryEventTransaction coordinates mutation and domain publication through IntegrationEventTransaction.

ExampleCatalog values under `Domain/Primitives` validate UUIDs, nullable positive target identifiers, and UTF-8 text.
Groups contain 1 to 32 characters. Keys contain 1 to 160 characters. Values contain 0 to 2048 characters.
Commands, aggregate creation, and reconstitution use these values. Aggregate changes validate every supplied value before mutation.
Details contain JSON with non-integer top-level member names. Nested objects and arrays retain their JSON shapes.
Detail construction verifies encoding and decoding within 64 levels before state changes. This bound leaves room for event envelopes.
Member names cannot start with NUL at any depth. Invalid details leave aggregate fields and previous command details unchanged.

Keep module-owned tests inside the module. Keep architecture, shared, infrastructure, and API tests under root `tests`.

Communicate across context boundaries with stable contracts. Prefer versioned integration events for asynchronous communication.

Basis: `resources/docs/1-bounded-contexts.html`.
