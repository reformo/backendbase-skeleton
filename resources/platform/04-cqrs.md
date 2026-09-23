# CQRS

Commands change state and return no result. Queries read data and return a declared result type.

## Contract rules

- Implement `Command` or `Query<TResult>`.
- Current commands and queries carry no handler attributes or handler imports.
- Map each contract to its handler in the owning context's `ServiceProvider::getHandlers()`.
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

Pass `RegistryHandlerResolver::class` as the second argument to the `config/dependencies.php` provider, or use its default. Pass a `HandlerResolver` to each bus constructor. Context `ServiceProvider::getHandlers()` methods supply the registry. A missing registry entry fails during dispatch. `AttributeHandlerResolver` remains available for separately attributed contracts, but it cannot dispatch the current commands and queries. The buses only resolve and call handlers. They provide no validation, authorization, logging, retry, transaction middleware, or asynchronous dispatch.

PHPDoc generics are not checked at runtime. Test each configured handler link and concrete handler contract.

Each HTTP action dispatches one command or query for one operation. A write action carries public identity in its command. The command handler resolves current state through a write port and owns missing-state decisions.

Basis: `resources/docs/2-cqrs.html`.
