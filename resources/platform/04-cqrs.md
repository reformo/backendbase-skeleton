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

Each HTTP action invokes one application operation. Normally, it dispatches one command or query. A write action can instead call the application orchestrator described below. A write command carries public identity. Its handler resolves current state through a write port and owns missing-state decisions.

## Client-visible write results

Command handlers and the command bus still return `void`. Select the smallest approach that satisfies the response contract:

1. **Identifier only:** For creation, generate the public identifier before dispatch and pass it in the command. For an existing resource, reuse its public identity. Return that identifier only after successful command execution. No orchestrator is needed. `RegisterAccount` uses this approach for its `accountUuid` response. `NewExample` uses it for the `Backendbase-Insert-Id` header.
2. **Resource representation:** The HTTP action can call one application orchestrator with validated, typed input. The orchestrator dispatches one command, waits for successful completion and commit, then reads through a query or read port. It returns a declared read model or immutable result object. The HTTP action selects the response body, status, and headers.

Place the orchestrator in the owning context's `Application` layer. Define its input and result contracts in that context's `Contracts` layer. Depend on bus interfaces and project-owned ports. Preserve context isolation, command authorization, and read authorization. A direct read-port call must enforce the same access policy as the corresponding query.

Keep write lookup, invariants, and transaction control in the command handler or its called application service. The response read must not select the write target. Do not move HTTP handling or SQL into the orchestrator. Do not mutate a command or use an event to carry response data back to its caller.

Define read consistency before selecting the second approach. An immediate representation requires a read source that can observe the committed write. An asynchronous projection or replica can lag. If that source cannot satisfy the response contract, use the identifier approach when the contract permits it. Otherwise, resolve the contract or read-source requirement before implementation. A later read represents resource state at read time; it does not guarantee the exact state at commit.

Define missing-result and read-failure behavior after commit. A failed response read does not roll back the completed command. Do not automatically repeat the command to recover the response. These approaches cannot return a handler-only value that was neither supplied beforehand nor persisted for an authorized read.

Verify identifier equality, no success response after command failure, and real runtime resolution. For an orchestrator, also verify write-before-read order, committed visibility, read authorization, and the defined missing-result and read-failure behavior. The orchestrator approach is permitted guidance; current create endpoints demonstrate the identifier approach.

Basis: `resources/docs/2-cqrs.html`.
