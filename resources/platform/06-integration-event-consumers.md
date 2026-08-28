# Integration Event Consumers

## External event flow

1. The processor appends `_Event` to the producer event name.
2. The registry resolves a message class by event name and version.
3. Valinor maps the payload into the versioned `EventMessage`.
4. The event manager dispatches registered external subscribers.
5. The inbox commits consumer database work and `processed_at` together.

## Subscriber types

- Internal subscribers implement `IntegrationEventSubscriber`.
- Internal subscribers run only after an explicit in-process dispatch.
- External subscribers implement `ExternalIntegrationEventSubscriber`.
- External subscribers live under `Application/ExternalIntegrationEventSubscribers/{SourceService}`.
- Versioned carriers live under `Contracts/ExternalIntegrationEvents/{Version}`.

Register each subscriber in the context `ServiceProvider`. External entries also require `messageFQCN` and `eventVersion`.

The inbox identity is `(consumer_name, message_id)`. A processed duplicate acknowledges without dispatch.

Missing metadata, unknown versions, missing subscribers, wrong interfaces, and mapping errors are permanent failures. Other subscriber or infrastructure exceptions are transient failures.

Test the actual producer payload against its registered consumer carrier. Derive the carrier registration from the real context service provider. Map through the real registry and dispatcher.

Basis: `resources/docs/3-integration-events.html`.
