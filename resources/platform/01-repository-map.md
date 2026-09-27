# Repository Map

| Concern | Location |
| --- | --- |
| Bounded contexts | `src/Backendbase/Domain/{ContextName}` |
| Framework-free shared contracts and values | `src/Backendbase/Shared` |
| Technology and HTTP adapters | `src/Backendbase/Infrastructure` |
| Reusable HTTP adapter support | `src/Backendbase/Infrastructure/Adapters/Http` |
| Operational console adapter | `src/Backendbase/Infrastructure/Adapters/Console` |
| Technical messaging coordination | `src/Backendbase/Infrastructure/Messaging` |
| Consumer-specific entry code | `src/Backendbase/Infrastructure/Inbound` |
| Reusable Doctrine adapter support | `src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine` |
| Typed runtime and adapter settings | `src/Backendbase/Infrastructure/Configuration` |
| Cross-module HTTP and JWT settings | `src/Backendbase/Shared/Configuration` |
| Example API adapter | `src/Backendbase/Infrastructure/Inbound/ExampleApi` |
| Shared configuration | `config/autoload` |
| Example API configuration | `config/example-api` |
| Dependency definitions | `config/dependencies.php`, `config/dependencies` |
| HTTP entry points | `public/index.php`, `public/example-api/index.php` |
| OpenAPI source | `resources/api-docs` |
| Bruno collections | `resources/bruno` |
| Migrations and seeders | `resources/database` |
| Local translations | `resources/i18n` |
| Platform tests | `tests` |
| Module-owned tests | `src/Backendbase/Domain/*/Tests` |
| Runtime cache | `var/cache` |
| Console entry point | `bin/backendbase` |
| Doctrine entry point | `bin/doctrine` |
| Coverage, documentation-link, and report checks | `bin/check-coverage.php`, `bin/check-documentation-links.php`, `bin/update-quality-report.php` |

Use the current `Infrastructure/Inbound` directories to find affected APIs. Scaffold an API before adding controllers to a non-existent API.

Basis: `resources/docs/0-project.html`, `resources/docs/1-bounded-contexts.html`.
