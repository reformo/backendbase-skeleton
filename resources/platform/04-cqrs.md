# CQRS

Commands change state and return no result. Queries read data and return a declared result type.

## Contract rules

- Implement `Command` or `Query<TResult>`.
- Add exactly one positional `#[CQRSHandler(HandlerClass::class)]` attribute.
- Carry only data required by one use case.
- Validate HTTP input before contract construction.
- Convert business values into enums or value objects at the boundary.
- Keep serialization stable through `toArray()`.

## Handler rules

- Command handlers load aggregates through write ports.
- Command handlers invoke aggregate behavior and control required transactions.
- Query handlers use read ports and return read models, pages, scalars, lists, or `null`.
- Query handlers must not mutate aggregates or create integration events.
- Put SQL only in read adapters.

The buses only resolve and call handlers. They provide no validation, authorization, logging, retry, transaction middleware, or asynchronous dispatch.

Handler attributes and PHPDoc generics are not fully checked at runtime. Test each attribute link and concrete handler contract.

A controller query followed by a command is not atomic. The command handler must reload state and enforce current invariants.

Basis: `resources/docs/2-cqrs.html`.
