# Integration Event Contracts

An integration event is a versioned business fact. Its name, version, and payload are compatibility contracts.

## Producer rules

- Implement `IntegrationEvent` and use `IntegrationEventTrait`.
- Set `EVENT_TYPE`, `EVENT_VERSION`, and `DELIVER_VIA_QUEUE`. Use `true` for local dispatch plus queue publication. Use `false` for local dispatch only.
- Name new events `{PascalCaseServiceName}_{PascalCaseEventClassName}`.
- Read the service name from `config/autoload/global.php`.
- Keep published event names stable.
- Put producer payloads under `Contracts/IntegrationEvents/{Version}`.
- Use explicit typed payload fields. Do not serialize aggregates or commands.
- Require an explicit `DateTimeImmutable` occurrence time in each event constructor.
- Read the application `Clock` inside the transaction after authoritative reads. Reuse the mutation instant for its related events.
- Preserve that instant in `occurredOn()` and outbox `occurred_at`. Keep outbox `created_at` as the persistence instant.
- Create a new version for an incompatible released payload change.
- Keep old contracts while old messages can still exist.

Use `IntegrationEventTransaction::execute()` to commit business writes, local subscriber writes, and the optional outbox row together. Its callback performs authoritative database work and returns the complete event. The transaction passes that event to `ContainerAwareEventManager::dispatchEvent()` before commit. Local subscribers run for both flag values. A `true` flag appends exactly one outbox row after local subscribers complete, including when no local subscriber is registered.

Producer dispatch requires an active transaction. The transaction wrapper, subscriber repositories, and outbox adapter must use the same database connection. Subscriber or outbox failures propagate and roll back database work. Do not perform network, filesystem, process, or direct broker work inside the callback or local subscribers. The queue consumer uses `dispatchExternalEvent()` and does not append the received event again.

The version-one `EntryAdded` producer publishes an outer `exampleId` and a nested `command` object. Its registered consumer carrier preserves this released shape with typed outer and nested data transfer objects.

The PHP context is `ExampleCatalog`, and PHP accessors use `entryId()`. Existing event names such as `Example_NewExampleAdded` and serialized `exampleId` keys remain stable. Version-one carrier constructor parameters retain `exampleId` for object mapping.

Keep an executable producer-to-consumer contract test. Map the producer event arguments through the real registry and dispatcher into the registered carrier.

Basis: `resources/docs/3-integration-events.html`.

Released payload keys remain stable when PHP fields are renamed. Event carriers declare framework-independent `INPUT_FIELD_ALIASES` when a source key differs.
The default map is empty. The queue adapter applies the selected carrier's aliases before typed reconstruction.
`GreetingRequestedPayload` maps released `fullname` to PHP `fullName` and continues to serialize `fullname`.
Keep mapping-library attributes out of domain and contract classes. Verify reconstruction through the real registry and dispatcher.
