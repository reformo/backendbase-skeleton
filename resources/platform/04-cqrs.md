# CQRS

Commands change state and return no result. Queries read data and return a declared result type.

## Contract rules

- Implement `Command` or `Query<TResult>`.
- In `attribute` mode, add exactly one `#[CQRSHandler(HandlerClass::class)]` attribute.
- In `registry` mode, map the contract to its handler in the owning context's `ServiceProvider::getHandlers()`.
- Carry only data required by one use case.
- Validate HTTP input before contract construction.
- Convert business values into enums or value objects at the boundary.
- Require typed `AccessControl` in each command or query that needs a named privilege.
- Keep serialization stable through `toArray()`.

## Handler rules

- Command handlers load aggregates through write ports.
- Privileged command and query handlers enforce named privileges before repository calls or external effects.
- Command handlers invoke aggregate behavior and control required transactions.
- Query handlers use read ports and return read models, pages, scalars, lists, or `null`.
- Query handlers must not mutate aggregates or create integration events.
- Put SQL only in read adapters.

Pass `AttributeHandlerResolver::class` or `RegistryHandlerResolver::class` as the second argument to the `config/dependencies.php` provider. The default is `RegistryHandlerResolver::class`. Both classes implement `HandlerResolver`. Context `ServiceProvider::getHandlers()` methods supply the registry. A missing registry entry or attribute fails during dispatch. The buses only resolve and call handlers. They provide no validation, authorization, logging, retry, transaction middleware, or asynchronous dispatch.

PHPDoc generics are not checked at runtime. Test each configured handler link and concrete handler contract.

Each HTTP action dispatches one command or query for one operation. A write action carries public identity in its command. The command handler resolves current state through a write port and owns missing-state decisions.

Basis: `resources/docs/2-cqrs.html`.
