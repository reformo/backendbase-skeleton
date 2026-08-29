# Backendbase architecture-verification pattern

## Role-to-target mapping

| Source area | Forbidden dependency in current Backendbase |
| --- | --- |
| Domain core | `Backendbase\Infrastructure` and context `Application` |
| Business layers | Any context `Adapters` dependency |
| One bounded context | A different `Backendbase\Domain` context |
| Business layers | Configured framework namespace prefixes |
| Shared | `Backendbase\Application`, `Backendbase\Domain`, and `Backendbase\Infrastructure` |
| Application | `Backendbase\Infrastructure` |
| Shared core | Configured framework namespace prefixes |
| Inbound adapter | Any concrete outbound adapter class |
| Outbound adapter | Any concrete inbound adapter class |

The business-layer selector excludes adapter paths and root context service providers. Production selectors exclude test paths.

## Intentional current-policy exceptions

Backendbase permits two narrow directions that can look like violations during manual review:

- Application code can use `Psr\Log\LoggerInterface` as a logging port. `FrameworkImportBoundaryTest` does not forbid the `Psr\Log` namespace.
- A command or query contract can reference its handler in the same bounded context through exactly one positional `#[CQRSHandler(HandlerClass::class)]` attribute.
- A domain event can reference one same-context listener through exactly one positional domain-listener attribute.

These are policy decisions in this repository. Do not copy them into a target project without checking its architecture tests. Neither exception permits domain-core code to import Application, nor one bounded context to import another context.

## Small boundary test

```php
#[Test]
public function businessLayersDoNotDependOnAdapters(): void
{
    $violations = ArchitectureDependencies::violations(
        BoundedContextDependencies::businessLayers(),
        static fn (string $file, string $dependency): bool =>
            str_starts_with($dependency, 'Acme\\Domain\\')
            && str_contains($dependency, '\\Adapters\\'),
    );

    self::assertSame([], $violations);
}
```

Replace the namespace after inspecting target autoloading. Reuse local scanner helpers rather than adding another parser.

## Review decisions

- Move a framework type behind a project-owned port when business code imports it.
- Move business decisions from an adapter to the domain instead of allowing the reverse dependency.
- Communicate across bounded contexts through stable contracts or integration events, not direct application or adapter imports.
- Keep container and implementation selection in composition roots.
- Treat a new exclusion as an architecture-policy change that needs explicit justification.
- Verify that every attribute target implements the required interface and resolves from the production container.

## Separate runtime reachability

Static dependency checks cannot prove that correct code is reachable. When a change adds or moves a registered type, test the applicable composition path separately:

- Load the real context provider and resolve each port binding.
- Dispatch a real command or query through the container-backed bus.
- Build the actual event registry and resolve subscriber metadata by event name and version.
- Collect the real HTTP routes or console command list and assert the new entry.

Do not weaken an architecture rule because composition fails. Fix the registration or dependency direction, then run both test modes again.

## Current source behavior and limitations

- The scanner uses `nikic/php-parser`, resolves PHP names, and ignores strings that only look like class names.
- A separate scanner finds constructor and static factory calls for the vendor-connection boundary.
- Domain purity currently targets directories named `Domain`, `Authorization`, and `Exception` under each context.
- Business-layer checks exclude `/Adapters/` and root `ServiceProvider.php` files.
- Bounded-context isolation derives context names from direct children of `src/Backendbase/Domain`.
- The framework-prefix list is explicit and can become stale when dependencies change.
- Static scanning cannot prove dynamically built class names, runtime calls, SQL behavior, or business invariants.
- Architecture tests complement focused runtime tests; they do not replace them.
- `AttributeTargetBoundaryTest` resolves each CQRS handler and domain listener from the production container.
- Other registration paths still need purpose-built composition evidence when they change.

## Verification map

```sh
vendor/bin/phpunit tests/Architecture/DomainPurityTest.php
vendor/bin/phpunit tests/Architecture/AdapterDirectionTest.php
vendor/bin/phpunit tests/Architecture/BoundedContextIsolationTest.php
vendor/bin/phpunit tests/Architecture/FrameworkImportBoundaryTest.php
vendor/bin/phpunit tests/Architecture/SharedDependencyBoundaryTest.php
vendor/bin/phpunit tests/Architecture/ApplicationDependencyBoundaryTest.php
vendor/bin/phpunit tests/Architecture/SharedCoreFrameworkBoundaryTest.php
vendor/bin/phpunit tests/Architecture/InboundAdapterDependencyBoundaryTest.php
vendor/bin/phpunit tests/Architecture/OutboundAdapterDependencyBoundaryTest.php
vendor/bin/phpunit tests/Architecture/AttributeTargetBoundaryTest.php
vendor/bin/phpunit tests/Architecture
vendor/bin/phpunit {RealCompositionTestPath}
composer phpstan
```

Create or select `{RealCompositionTestPath}` for the changed provider, container, bus, route map, command list, or registry. Do not cite the partial current tests as full runtime reachability evidence.

## Source provenance

- `resources/docs/project.md`
- `resources/docs/0-project.html`
- `resources/docs/1-bounded-contexts.html`
- `resources/docs/10-testing-and-quality.html`
- `resources/platform/02-architecture.md`
- `src/Backendbase/Shared/CQRS/Attributes/CQRSHandler.php`
- `src/Backendbase/Infrastructure/Adapters/CQRS/ContainerAwareCommandBus.php`
- `src/Backendbase/Infrastructure/Adapters/CQRS/ContainerAwareQueryBus.php`
- `config/dependencies/bounded-contexts.php`
- `config/dependencies/modules.php`
- `tests/Architecture/DomainPurityTest.php`
- `tests/Architecture/AdapterDirectionTest.php`
- `tests/Architecture/BoundedContextIsolationTest.php`
- `tests/Architecture/FrameworkImportBoundaryTest.php`
- `tests/Architecture/SharedDependencyBoundaryTest.php`
- `tests/Architecture/ApplicationDependencyBoundaryTest.php`
- `tests/Architecture/SharedCoreFrameworkBoundaryTest.php`
- `tests/Architecture/InboundAdapterDependencyBoundaryTest.php`
- `tests/Architecture/OutboundAdapterDependencyBoundaryTest.php`
- `tests/Architecture/AttributeTargetBoundaryTest.php`
- `tests/Architecture/Support/ArchitectureDependencies.php`
- `tests/Architecture/Support/BoundedContextDependencies.php`
- `tests/Architecture/Support/PhpDependencyScanner.php`
- `tests/Domain/ExampleBoundedContext/ServiceProviderTest.php`
- `tests/Infrastructure/Adapters/CQRS`
- `tests/Infrastructure/UseCase/ExampleApi/ModuleRoutingTest.php`
- `.github/workflows/quality-gates.yml`
