# Integration Event Contracts

An integration event is a versioned business fact. Its name, version, and payload are compatibility contracts.

## Producer rules

- Implement `IntegrationEvent` and use `IntegrationEventTrait`.
- Set `EVENT_TYPE`, `EVENT_VERSION`, and `IS_MESSAGING_EVENT = true`.
- Name new events `{PascalCaseServiceName}_{PascalCaseEventClassName}`.
- Read the service name from `config/autoload/global.php`.
- Keep published event names stable.
- Put producer payloads under `Contracts/IntegrationEvents/{Version}`.
- Use explicit typed payload fields. Do not serialize aggregates or commands.
- Create a new version for an incompatible released payload change.
- Keep old contracts while old messages can still exist.

Use `IntegrationEventTransaction::execute()` to commit the domain mutation and outbox row together. Its callback performs authoritative database work and returns the complete event. Do not perform network, filesystem, process, or direct broker work inside the callback.

The version-one `NewExampleAdded` producer publishes an outer `exampleId` and a nested `command` object. Its registered consumer carrier preserves this released shape with typed outer and nested data transfer objects.

Keep an executable producer-to-consumer contract test. Map the producer event arguments through the real registry and dispatcher into the registered carrier.

Basis: `resources/docs/3-integration-events.html`.
