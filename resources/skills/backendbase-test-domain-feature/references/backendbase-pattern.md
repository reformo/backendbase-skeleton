# Backendbase domain-feature test pattern

## Role-to-target mapping

| Risk | Test mode | Main evidence |
| --- | --- | --- |
| Value invariant or aggregate transition | Domain | Pure object construction and behavior |
| Command or query public contract | CQRS contract | Accessors, nullable meaning, and exact serialization |
| Handler orchestration | CQRS handler | Port calls, aggregate operation, transaction, and event order |
| ORM or DBAL behavior | Repository | Production metadata, mapping, SQL, filters, and rollback |
| Multiple use cases through buses | Lifecycle | Real buses with memory ports and deterministic doubles |
| Container or registry wiring | Composition and registration | Real provider loading, container resolution, bus dispatch, or registry lookup |
| Dependency direction | Architecture | Parsed-symbol boundary tests |

## Small domain test

```php
#[Test]
public function itDiscontinuesAnActiveItem(): void
{
    $item = CatalogItem::create(CatalogItemId::create(), new ItemName('Desk lamp'));

    $item->discontinue();

    self::assertTrue($item->isDiscontinued());
}
```

Add invalid and repeated-operation cases when the domain contract rejects them.

## Handler-order evidence

For transactional command behavior, record observable calls in the test and assert the required order, for example:

```php
self::assertSame(
    ['transaction-start', 'repository', 'domain-event', 'transaction-end'],
    $calls,
);
```

Assert only ordering that is part of correctness.

## Repository setup

- Define only the result, absence, error, and other behavior declared by the port.
- Run that contract suite unchanged against every adapter for the port.
- Register the same Doctrine custom types used by production.
- Create ORM configuration from the production entity directory.
- Build an isolated schema through `SchemaTool` and all production metadata.
- Instantiate each adapter behind the same read and write port accessors.
- Keep mapping, null storage, malformed rows, SQL, generated columns, and rollback in Doctrine-specific tests.

## Lifecycle setup

- Share one memory store between read and write adapters.
- Bind production port interfaces to those adapters in the test container.
- Stub transaction callbacks to execute their work.
- Stub domain publishers or external ports deterministically.
- Use the Infrastructure `ContainerAwareCommandBus` and `ContainerAwareQueryBus` adapters so handler attributes are exercised.

## Composition and registration setup

Use this mode when production code can compile but remain unreachable:

- Load the target project's real dependency providers or test composition root.
- Resolve each new handler and every constructor dependency from the container.
- Dispatch one real command or query through the container-backed bus when handler metadata changed.
- Inspect the actual context-provider output for port bindings and subscriber metadata.
- Exercise the real registry or route collection when subscribers, console commands, modules, or routes changed.

A direct handler unit test proves orchestration only. An architecture test proves dependency direction only. Neither proves runtime registration.

## Test ownership in the current repository

`phpunit.xml` includes both root `tests` and `src/Backendbase/Domain/*/Tests`. All movable Example context tests are context-owned.

For new work in an unmodified Backendbase project:

- Keep tests that must move with a bounded context under that context's `Tests` directory.
- Keep platform, Shared, infrastructure, API, functional, and architecture tests under root `tests`.
- Use `src/Backendbase/Domain/ExampleBoundedContext/Tests` files as verified examples for their test roles.

## Current source behavior and limitations

- The Example repository contract suite runs unchanged against memory and Doctrine adapters. Its behavior is specific to the Example port.
- Doctrine repository tests use SQLite with production metadata. They do not prove every MySQL generated-column, lock, or migration behavior.
- `.github/workflows/quality-gates.yml` runs Composer validation and audit, `composer test`, PHPStan, cyclomatic complexity, PHPCS, OpenAPI validation, generation, and a generated-file diff check.
- `.github/workflows/security-checks.yml` runs Semgrep and a prepared OpenAPI-based dynamic application security test. These checks need their configured services and tools.
- `.github/workflows/release-artifact.yml` repeats release gates before it builds an immutable artifact from a reviewed revision.
- Rector is neither configured nor installed. Do not report it as passing.
- `composer test` runs PHPUnit with coverage and deployment shell-script checks.

## Verification order

```sh
vendor/bin/phpunit src/Backendbase/Domain/Catalog/Tests/Domain/CatalogItemTest.php
vendor/bin/phpunit src/Backendbase/Domain/Catalog/Tests
vendor/bin/phpunit tests/Functional/CatalogLifecycleTest.php
vendor/bin/phpunit tests/Architecture
composer phpstan
composer complexity
composer cs-check
composer test
```

Use existing target paths. Report required checks that could not run and their blockers. Omit checks unrelated to the changed behavior.

## Source provenance

- `resources/docs/project.md`
- `resources/docs/10-testing-and-quality.html`
- `resources/platform/17-testing.md`
- `phpunit.xml`
- `composer.json`
- `.github/workflows/quality-gates.yml`
- `.github/workflows/security-checks.yml`
- `.github/workflows/release-artifact.yml`
- `src/Backendbase/Domain/ExampleBoundedContext/Tests/Domain/ExampleTest.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Tests/Contracts/CommandAndQueryContractsTest.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Tests/Application/CommandHandlers/AddNewExampleHandlerTest.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Tests/Adapters/Persistence/Doctrine/DoctrineExampleRepositoryTestCase.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Tests/Adapters/Persistence/Doctrine/ExampleRepositoryTest.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Tests/Adapters/Persistence/Memory/ExampleRepositoryTest.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Tests/Adapters/Persistence/ExampleRepositoryContract.php`
- `tests/Functional/ExampleLifecycleTest.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Tests/ExampleServiceTest.php`
