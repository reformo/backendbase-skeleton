# Bounded Contexts

A bounded context owns one business language. Create it under `src/Backendbase/Domain/{PascalCaseContextName}`.

## Module shape

- `Domain`: aggregates, value objects, enums, and rules.
- `Contracts`: commands, queries, ports, read models, and event contracts.
- `Application`: handlers, domain listeners, and integration subscribers.
- `Adapters/Persistence/Doctrine`: production read and write adapters.
- `Adapters/Persistence/Memory`: fast test adapters.
- `Tests`: tests owned by the movable module.
- `ServiceProvider.php`: port bindings and subscriber metadata.

Boot discovers only `src/Backendbase/Domain/*/ServiceProvider.php`. A normal context needs no Composer mapping change.

Use `ExampleBoundedContext` as the reference. No `Content` context currently exists. `IdentityAndAccess` does not currently use this provider pattern.

Keep module-owned tests inside the module. Keep architecture, shared, infrastructure, and API tests under root `tests`.

Communicate across context boundaries with stable contracts. Prefer versioned integration events for asynchronous communication.

Basis: `resources/docs/1-bounded-contexts.html`.
