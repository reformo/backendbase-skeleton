# Backendbase bounded-context pattern

Use this reference after target-project discovery. It is a decision guide, not a file template.

## Role-to-target mapping

| Role | Backendbase target |
| --- | --- |
| Business state and rules | `src/Backendbase/Domain/Catalog/Domain` |
| Commands, queries, ports, read models, events | `src/Backendbase/Domain/Catalog/Contracts` |
| Use-case handlers and listeners | `src/Backendbase/Domain/Catalog/Application` |
| Doctrine and memory implementations | `src/Backendbase/Domain/Catalog/Adapters` |
| Production bindings and subscriber metadata | `src/Backendbase/Domain/Catalog/ServiceProvider.php` |
| Movable module tests | `src/Backendbase/Domain/Catalog/Tests` |
| Existing root adapter-aware evidence and architecture policy | `tests/Domain/ExampleBoundedContext` and `tests/Architecture` |

`Catalog` is an illustrative context name. Resolve the real name from the requested business capability. Create only rows required by that capability.

## Minimal provider shape

```php
<?php

declare(strict_types=1);

namespace Acme\Domain\Catalog;

use Acme\Domain\Catalog\Adapters\Persistence\Doctrine\CatalogWriteRepository as DoctrineCatalogWriteRepository;
use Acme\Domain\Catalog\Contracts\CatalogWriteRepository;
use Backendbase\Shared\ServiceProvider as PlatformServiceProvider;

final class ServiceProvider implements PlatformServiceProvider
{
    public static function getDefinitions(): iterable
    {
        return [CatalogWriteRepository::class => DoctrineCatalogWriteRepository::class];
    }

    public static function getIntegrationEventSubscribers(): iterable
    {
        return [];
    }
}
```

Replace `Acme` only after reading the target Composer mapping. An empty subscriber list is correct when no event behavior was requested.

## Test ownership

Current `phpunit.xml` includes both root `tests` and `src/Backendbase/Domain/*/Tests`.

- Put new tests that must move with a bounded context under that context's `Tests` directory.
- Put platform, Shared, infrastructure, API, functional, and architecture tests under root `tests`.
- The current repository also contains legacy and adapter-aware Example tests under `tests/Domain/ExampleBoundedContext`. Use them as behavior references for their test role.
- Do not move existing tests only to normalize layout during an unrelated context change.

Adapt these ownership rules to the target project's configured test roots. Do not create a source-owned test directory if the target runner cannot discover it.

## Provider composition test

Do not stop at calling `ServiceProvider::getDefinitions()` directly. Use the target project's real provider-discovery path or a faithful test composition root and verify:

1. the provider is discovered from its actual path;
2. every declared port resolves to the intended adapter;
3. every adapter dependency resolves;
4. requested internal and external subscriber metadata reaches the actual registry;
5. no unrequested subscriber, event, adapter, or schema surface was added.

Architecture tests prove dependency direction. This composition test separately proves runtime reachability.

## Current source behavior and limitations

- Boot scans only direct `src/Backendbase/Domain/*/ServiceProvider.php` files.
- Provider discovery uses relative paths and expects the repository root as the current directory.
- Provider glob order is unsorted. Duplicate port keys can overwrite one another.
- `PathFinder::doctrineEntityPaths()` sees direct context entities and one nested module level.
- The Doctrine CLI schema filter currently collects service table names only from direct context entity folders. Prefer direct context placement unless the CLI is updated too.
- `ExampleBoundedContext` is the complete source reference. No `Content` context currently exists. `IdentityAndAccess` uses different wiring.

## Verification map

```sh
vendor/bin/phpunit src/Backendbase/Domain/Catalog/Tests
vendor/bin/phpunit tests/Architecture
composer phpstan
composer complexity
composer cs-check
```

Use target paths. Do not create empty test directories only to satisfy this list.

## Source provenance

This pattern was checked against:

- `resources/docs/project.md`
- `resources/docs/0-project.html`
- `resources/docs/1-bounded-contexts.html`
- `resources/platform/00-platform.md`
- `resources/platform/02-architecture.md`
- `resources/platform/03-bounded-contexts.md`
- `resources/platform/24-feature-workflow.md`
- `src/Backendbase/Domain/ExampleBoundedContext/ServiceProvider.php`
- `src/Backendbase/Shared/ServiceProvider.php`
- `config/dependencies/bounded-contexts.php`
- `config/dependencies/modules.php`
- `src/Backendbase/Shared/Helpers/PathFinder.php`
- `phpunit.xml`
- `tests/Domain/ExampleBoundedContext/ServiceProviderTest.php`
- `src/Backendbase/Domain/ExampleBoundedContext/Tests/ExampleServiceTest.php`
- `tests/Architecture`
- `.github/workflows/quality-gates.yml`
