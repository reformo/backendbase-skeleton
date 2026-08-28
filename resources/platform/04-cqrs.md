# CQRS

Commands change state and return no result. Queries read data and return a declared result type.

## Contract rules

- Implement `Command` or `Query<TResult>`.
- Add exactly one positional `#[CQRSHandler(HandlerClass::class)]` attribute.
- Carry only data required by one use case.
- Validate HTTP input before contract construction.
- Convert business values into enums or value objects at the boundary.
- Require typed `AccessControl` in write commands that need a named privilege.
- Keep serialization stable through `toArray()`.

## Handler rules

- Command handlers load aggregates through write ports.
- Command handlers enforce named privileges before transactions, repository calls, or external effects.
- Command handlers invoke aggregate behavior and control required transactions.
- Query handlers use read ports and return read models, pages, scalars, lists, or `null`.
- Query handlers must not mutate aggregates or create integration events.
- Put SQL only in read adapters.

The buses only resolve and call handlers. They provide no validation, authorization, logging, retry, transaction middleware, or asynchronous dispatch.

Handler attributes and PHPDoc generics are not fully checked at runtime. Test each attribute link and concrete handler contract.

Each HTTP action dispatches one command or query for one operation. A write action carries public identity in its command. The command handler resolves current state through a write port and owns missing-state decisions.

Basis: `resources/docs/2-cqrs.html`.
