# ExampleCatalog Instructions

Apply the [root rules](../../../../AGENTS.md) and relevant [platform guidance](../../../../resources/platform/README.md).
This context is the complete business-module reference. Production contexts use their own business names.

## Context rules

- Use `Entry` terminology for PHP records and operations in this context.
- Pass explicit instants to Entry creation, change, and removal. `EntryWrites` reads the injected UTC clock during transactional work.
- Preserve stored timestamps during reconstitution.
- Keep mutation and domain-event publication inside `EntryEventTransaction`, which delegates to `IntegrationEventTransaction`.
- Preserve released event names and version-one `exampleId` payload keys. PHP accessors use `entryId()`.
- Keep `QueueGreeting` authorization on `example.add`. Greeting HTTP endpoints remain absent.

## Change locations

- Bind ports, handlers, and subscribers in [ServiceProvider.php](ServiceProvider.php).
- Keep Example HTTP handlers in [ExampleApi](../../Infrastructure/Inbound/ExampleApi/Controllers/Example).
- Use [event contracts](../../../../resources/platform/05-integration-event-contracts.md) for producer and consumer changes.
- Use [persistence guidance](../../../../resources/platform/09-persistence.md) for Doctrine and memory adapter changes.

## Verification and maintenance

Run commands from the repository root. Select checks through [testing guidance](../../../../resources/platform/17-testing.md).
Use the affected tests under [Tests](Tests), the [provider test](Tests/ServiceProviderTest.php), and [Entry lifecycle tests](../../../../tests/Functional/EntryLifecycleTest.php) as required.
Include [architecture tests](../../../../tests/Architecture) when dependencies or layer placement change.

Update this file when context ownership, invariants, contracts, or verification entry points change.
Keep detailed explanations in platform documents and decisions in architecture decision records.
