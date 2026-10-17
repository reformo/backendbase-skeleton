# ExampleCatalog Instructions

Apply the [root rules](../../../../AGENTS.md) and relevant [platform guidance](../../../../resources/platform/README.md).
This context is the complete business-module reference. Production contexts use their own business names.

## Context rules

- Use `Entry` terminology for PHP records and operations in this context.
- Enforce UUIDs, positive nullable target identifiers, and UTF-8 field limits through context-owned values under `Domain/Primitives`.
- Treat `example_table.details` as an explicit exception to the default typed JSON-column mapping. Retain `EntryDetails` and `JSON_OBJECT` shape preservation.
- Preserve accepted text and JSON details. Reject non-object HTTP details and integer top-level detail names.
- Validate detail encoding and decoding within 64 levels before mutation. Reject NUL-prefixed member names at every depth.
- Order entry pages by creation time and UUID in both repository adapters.
- Pass explicit instants to Entry creation, change, and removal. `EntryWrites` reads the injected UTC clock during transactional work.
- Preserve stored timestamps during reconstitution.
- Keep mutation and domain-event publication inside `EntryEventTransaction`, which delegates to `IntegrationEventTransaction`.
- Preserve released event names and version-one `exampleId` payload keys. PHP accessors use `entryId()`.
- Require the existing USER bearer authentication for every Example-tagged route except `getExampleByCriteria`. Retain API-key protection on all Example routes.
- Keep `QueueGreeting` authorization on `example.add`. Greeting HTTP endpoints remain absent.

## Change locations

- Bind ports, handlers, and subscribers in [ServiceProvider.php](ServiceProvider.php).
- Keep operations with the `Example` OpenAPI tag in [ExampleApi's Example module](../../Infrastructure/Inbound/ExampleApi/Controllers/Example).
- Add one controller per operation under `Handlers`. Register it in the module's `ModuleConfig.php`, following the [API controller organization rule](../../../../AGENTS.md#api-controller-organization).
- Use [event contracts](../../../../resources/platform/05-integration-event-contracts.md) for producer and consumer changes.
- Use [persistence guidance](../../../../resources/platform/09-persistence.md) for Doctrine and memory adapter changes.

## Verification and maintenance

Run commands from the repository root. Select checks through [testing guidance](../../../../resources/platform/17-testing.md).
Use the affected tests under [Tests](Tests), the [provider test](Tests/ServiceProviderTest.php), and [Entry lifecycle tests](../../../../tests/Functional/EntryLifecycleTest.php) as required.
Include [architecture tests](../../../../tests/Architecture) when dependencies or layer placement change.

Update this file when context ownership, invariants, contracts, or verification entry points change.
Keep detailed explanations in platform documents and decisions in architecture decision records.
