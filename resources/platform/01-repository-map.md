# Repository Map

| Concern | Location |
| --- | --- |
| Bounded contexts | `src/Backendbase/Domain/{ContextName}` |
| Shared contracts and services | `src/Backendbase/Shared` |
| Technology and HTTP adapters | `src/Backendbase/Infrastructure` |
| Example API adapter | `src/Backendbase/Infrastructure/UseCase/ExampleApi` |
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

Use the current `Infrastructure/UseCase` directories to find affected APIs. Scaffold an API before adding controllers to a non-existent API.

Basis: `resources/docs/0-project.html`, `resources/docs/1-bounded-contexts.html`.
