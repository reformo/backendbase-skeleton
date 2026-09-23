# Backendbase console command pattern

This reference is self-contained. It describes the verified Backendbase pattern and how to adapt it without copying repository identities.

## Target discovery

Resolve only unknown facts needed by the affected behavior. Reuse verified facts while their sources remain unchanged.

Before editing:

1. Read every `AGENTS.md` that applies to the target files.
2. Inspect `composer.json` for PHP version, packages, scripts, and PSR-4 roots.
3. Locate the console entry point, command registration, container providers, tests, and the nearest command with similar effects.
4. Record the target namespace, layer names, command naming style, and test layout.
5. Check for existing uncommitted work. Preserve unrelated changes.

Do not assume `Backendbase\`, `bin/backendbase`, `config/commands.php`, or the example queue names exist in the target.

## Role to target mapping

| Role | Backendbase reference | Target decision |
| --- | --- | --- |
| Executable bootstrap | `bin/backendbase` | Reuse the target console bootstrap. |
| Command class | `Infrastructure/Adapters/Console/{Area}` | Use the target infrastructure or delivery layer. |
| Application capability | Injected project-owned interface | Reuse or add a narrow port outside the command. |
| Container registration | `config/commands.php` | Follow the target registration mechanism. |
| Focused test | Symfony `CommandTester` | Place it with the target's console tests. |
| Runtime owner | Scheduler or supervisor | Classify before implementation. |

## Workflow

1. Write the command contract: name, arguments, options, validation, output, exit codes, and side effects.
2. Inject a capability. Keep business rules out of the command.
3. Validate all command-line input before calling that capability.
4. Return `Command::INVALID` for invalid input, `FAILURE` for completed work that reports failure, and `SUCCESS` only for success.
5. Register the class in the existing container and command list.
6. Add tests for valid input, each invalid boundary, output, exit status, and the absence of calls after invalid input.
7. Classify finite commands for scheduling and long-running commands for supervision.

## Small implementation example

The names below are illustrative. Derive the namespace and service names from the target.

```php
final class RebuildSearchIndex extends Command
{
    public function __construct(private readonly SearchIndexRebuilder $rebuilder)
    {
        parent::__construct('search:index:rebuild');
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum records.', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (string) $input->getOption('limit');
        if (! ctype_digit($limit) || (int) $limit < 1 || (int) $limit > 1000) {
            $output->writeln('<error>The limit must be between 1 and 1000.</error>');

            return self::INVALID;
        }

        $rebuilt = $this->rebuilder->rebuild((int) $limit);
        $output->writeln('Rebuilt: ' . $rebuilt . '.');

        return self::SUCCESS;
    }
}
```

Backendbase registration uses this shape:

```php
$commands = [
    RebuildSearchIndex::class => autowire(RebuildSearchIndex::class),
];

$containerBuilder->addDefinitions($commands);

return array_keys($commands);
```

Use the target container's native equivalent when it differs.

## Verified Backendbase invariants and limits

- The console bootstrap changes to the repository root before loading relative configuration.
- Shared configuration loads before optional console configuration.
- Queue maintenance commands validate limits before invoking their ports.
- Finite outbox commands communicate unhealthy state with a nonzero exit code.
- `Backendbase\Infrastructure\Adapters\Console\Command::logMessage()` uses the first alias. A subclass must set an alias before calling it.
- Queue consumers are long-running. A process supervisor must own them.
- Command registration is explicit. Creating a class alone does not expose it.

## Authorization boundary

Creating command code and tests does not authorize running destructive maintenance, installing schedules, changing a supervisor, or accessing a live dependency. Reuse explicit authority for the same operation, target, and effects. Ask only when that authority is missing or the scope changes.

## Verification

Select checks for the changed behavior and target policy. Command lists are examples, not a fixed sequence. Keep required static-analysis, complexity, and style gates for code changes. Reuse successful results while relevant inputs and the environment remain unchanged.

Select focused command tests and required project gates. Expand coverage when registration or bootstrap changes. Available Backendbase examples:

```sh
vendor/bin/phpunit tests/Infrastructure/Adapters/Console
vendor/bin/phpunit tests/Architecture
bin/backendbase list
composer phpstan
composer complexity
composer cs-check
```

Adapt paths and the executable name to the target. Run the full suite for broad registration or bootstrap changes.

## Completion report

Include:

- command name and purpose;
- registration file;
- argument and option limits;
- exit-code behavior;
- finite or long-running classification;
- tests and quality checks run;
- blocked required checks and their causes;
- external actions that were not performed.

## Provenance

Verified on 2026-08-25 from:

- `bin/backendbase`
- `config/commands.php`
- `src/Backendbase/Infrastructure/Adapters/Console/Command.php`
- `src/Backendbase/Infrastructure/Adapters/Console/Queue/RelayOutboxMessages.php`
- `src/Backendbase/Infrastructure/Adapters/Console/Queue/CleanupIntegrationMessages.php`
- `src/Backendbase/Infrastructure/Adapters/Console/Queue/ContainerAwareQueueConsumer.php`
- `tests/Infrastructure/Adapters/Console/CommandTest.php`
- `tests/Infrastructure/Adapters/Console/Queue/QueueMaintenanceCommandsTest.php`
- `resources/platform/01-repository-map.md`
- `resources/platform/13-queue-runtime.md`
- `resources/docs/4-messaging-and-queues.html`
