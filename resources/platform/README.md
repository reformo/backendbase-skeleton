# Backendbase Agent Context

Use this directory as modular prompt context for Backendbase Core. Each file covers one concern and can be included independently.

## Select context by task

Start with the requested outcome and the nearest source or document. Use the table to resolve unknown facts and affected constraints.

- Read `00-platform.md` when the stack, entry point, or project command is unknown.
- Read `02-architecture.md` when code placement, dependencies, or architectural boundaries are affected.
- Use `01-repository-map.md` when the relevant files are not known.
- Use `24-feature-workflow.md` when several implementation surfaces need coordination.
- Read [the skill-authoring rules](../skills/AGENTS.md) when creating or changing reusable skills.
- A prose-only correction needs the affected document and its checks. It does not need platform or implementation skills.
- Read the detailed HTML guides only when a selected summary does not answer a required question.

Reuse guidance and verified project facts already in context. When scope expands, load only the new relevant guidance and reassess affected decisions.

## Compliance and completion

Platform design rules and applicable skill invariants remain requirements. Current source establishes behavior; it does not authorize a design-rule exception.

Report a conflict before choosing an implementation that would violate a rule. Request a decision only when existing instructions or authorization do not resolve it.

Use the root `AGENTS.md` for authorization and completion boundaries. Use `17-testing.md` to coordinate verification once across the task.

Before delivery, confirm the changed surfaces follow their applicable rules. Report relevant evidence and exceptions without listing unrelated surfaces or unused skills.

## Task routing

| Affected concern | Consult when needed |
| --- | --- |
| Locate a change | `01-repository-map.md` |
| Coordinate a feature | `02-architecture.md`, `24-feature-workflow.md` |
| Domain feature | `03-bounded-contexts.md`, `04-cqrs.md`, `09-persistence.md`, `17-testing.md`, `24-feature-workflow.md` |
| HTTP endpoint | `07-http-api.md`, `08-api-contracts.md`, `14-security.md`, `16-errors-observability.md`, `17-testing.md` |
| Database change | `09-persistence.md`, `10-schema-changes.md`, `17-testing.md` |
| Integration event | `05-integration-event-contracts.md`, `06-integration-event-consumers.md`, `11-messaging-outbox.md`, `12-messaging-consumers.md`, `17-testing.md` |
| Queue operation | `11-messaging-outbox.md`, `12-messaging-consumers.md`, `13-queue-runtime.md`, `15-configuration.md`, `16-errors-observability.md` |
| Authentication | `07-http-api.md`, `08-api-contracts.md`, `14-security.md`, `16-errors-observability.md`, `17-testing.md` |
| Configuration | `15-configuration.md`, plus the affected adapter file |
| External service | `15-configuration.md`, `19-external-services.md`, `17-testing.md` |
| Notification | `12-messaging-consumers.md`, `13-queue-runtime.md`, `20-notifications.md`, `17-testing.md` |
| Translation | `21-i18n.md`, `17-testing.md` |
| Shared value or mapping | `22-shared-primitives.md`, `23-object-mapping.md`, `17-testing.md` |
| Deployment | `13-queue-runtime.md`, `15-configuration.md`, `16-errors-observability.md`, `18-deployment.md` |

## Skill routing

Select the smallest skill that supplies a needed procedure or invariant. Add another skill only when it supplies relevant guidance not already covered.

Use `backendbase-implement-feature` for coordinated changes across architectural surfaces. Use `backendbase-verify-change` for a requested cross-layer review or a risk that needs that review. Ordinary completion does not require loading both.

Read only the selected reference sections needed for the task. Reuse established discovery and successful checks across skills. Adapt Backendbase examples to the target project.

### Orchestration and verification

| Skill | Use it for |
| --- | --- |
| [`backendbase-implement-feature`](../skills/backendbase-implement-feature/SKILL.md) | Coordinate one feature across several architecture layers. |
| [`backendbase-test-domain-feature`](../skills/backendbase-test-domain-feature/SKILL.md) | Add focused domain, CQRS, repository, lifecycle, or composition tests. |
| [`backendbase-verify-architecture`](../skills/backendbase-verify-architecture/SKILL.md) | Verify dependency direction and bounded-context isolation. |
| [`backendbase-verify-change`](../skills/backendbase-verify-change/SKILL.md) | Review a cross-layer change when requested or when its risk requires it. |
| [`cyclomatic-complexity`](../skills/cyclomatic-complexity/SKILL.md) | Measure and reduce control-flow complexity in a touched function. |

### Domain and CQRS

| Skill | Use it for |
| --- | --- |
| [`backendbase-add-bounded-context`](../skills/backendbase-add-bounded-context/SKILL.md) | Create a new business capability boundary. |
| [`backendbase-model-domain-behavior`](../skills/backendbase-model-domain-behavior/SKILL.md) | Add aggregate rules, state changes, factories, or domain enums. |
| [`backendbase-add-domain-value-object`](../skills/backendbase-add-domain-value-object/SKILL.md) | Add a typed business value or identifier. |
| [`backendbase-add-command`](../skills/backendbase-add-command/SKILL.md) | Add one synchronous state-changing CQRS use case. |
| [`backendbase-add-query`](../skills/backendbase-add-query/SKILL.md) | Add one CQRS read use case and its declared result. |
| [`backendbase-add-domain-event`](../skills/backendbase-add-domain-event/SKILL.md) | Add a synchronous in-process domain event and listener. |
| [`backendbase-add-domain-problem`](../skills/backendbase-add-domain-problem/SKILL.md) | Add a stable expected failure with a Problem Details contract. |

### Persistence

| Skill | Use it for |
| --- | --- |
| [`backendbase-add-doctrine-write-adapter`](../skills/backendbase-add-doctrine-write-adapter/SKILL.md) | Persist an aggregate through Doctrine ORM. |
| [`backendbase-add-doctrine-read-adapter`](../skills/backendbase-add-doctrine-read-adapter/SKILL.md) | Implement a DBAL projection and persisted-row mapper. |
| [`backendbase-add-memory-adapter`](../skills/backendbase-add-memory-adapter/SKILL.md) | Add an in-memory port adapter for deterministic tests. |
| [`backendbase-add-migration`](../skills/backendbase-add-migration/SKILL.md) | Generate and review an approved schema migration. |
| [`backendbase-add-seeder`](../skills/backendbase-add-seeder/SKILL.md) | Add approved, stable, idempotent reference data. |

### HTTP and API contracts

| Skill | Use it for |
| --- | --- |
| [`backendbase-add-use-case-api`](../skills/backendbase-add-use-case-api/SKILL.md) | Scaffold a new consumer API surface. |
| [`backendbase-add-api-module`](../skills/backendbase-add-api-module/SKILL.md) | Add a route-prefix module to an existing API. |
| [`backendbase-add-api-endpoint`](../skills/backendbase-add-api-endpoint/SKILL.md) | Implement one endpoint in an existing API module. |
| [`backendbase-secure-api-endpoint`](../skills/backendbase-secure-api-endpoint/SKILL.md) | Apply API-key, bearer, and ACL policy to an operation. |
| [`backendbase-add-http-middleware`](../skills/backendbase-add-http-middleware/SKILL.md) | Add or change PSR-15 middleware and its order. |
| [`backendbase-add-openapi-operation`](../skills/backendbase-add-openapi-operation/SKILL.md) | Add or change one OpenAPI operation. |
| [`backendbase-add-bruno-api-test`](../skills/backendbase-add-bruno-api-test/SKILL.md) | Add an executable API request or lifecycle test. |

### Transactional messaging and queues

| Skill | Use it for |
| --- | --- |
| [`backendbase-install-transactional-messaging`](../skills/backendbase-install-transactional-messaging/SKILL.md) | Install the outbox, inbox, and consumer failure foundation. |
| [`backendbase-add-integration-event-producer`](../skills/backendbase-add-integration-event-producer/SKILL.md) | Add a versioned event producer and transactional outbox write. |
| [`backendbase-evolve-integration-event-contract`](../skills/backendbase-evolve-integration-event-contract/SKILL.md) | Evolve a released event contract without breaking retained messages. |
| [`backendbase-add-internal-integration-event-subscriber`](../skills/backendbase-add-internal-integration-event-subscriber/SKILL.md) | Add a synchronous internal integration-event subscriber. |
| [`backendbase-add-external-integration-event-subscriber`](../skills/backendbase-add-external-integration-event-subscriber/SKILL.md) | Add a versioned queue-delivered event carrier and subscriber. |
| [`backendbase-add-queue-message-processor`](../skills/backendbase-add-queue-message-processor/SKILL.md) | Add message validation and acknowledge, retry, or reject behavior. |
| [`backendbase-add-queue-transport-driver`](../skills/backendbase-add-queue-transport-driver/SKILL.md) | Add a broker transport adapter. |
| [`backendbase-add-queue-consumer-runtime`](../skills/backendbase-add-queue-consumer-runtime/SKILL.md) | Expose an existing processor as a console worker. |
| [`backendbase-add-messaging-operations`](../skills/backendbase-add-messaging-operations/SKILL.md) | Add relay, status, cleanup, scheduling, and health operations. |

### External capabilities

| Skill | Use it for |
| --- | --- |
| [`backendbase-add-external-service`](../skills/backendbase-add-external-service/SKILL.md) | Add a project-owned port and vendor API adapter. |
| [`backendbase-add-object-storage-capability`](../skills/backendbase-add-object-storage-capability/SKILL.md) | Add object download, upload, or signed browser upload. |
| [`backendbase-add-notification-provider`](../skills/backendbase-add-notification-provider/SKILL.md) | Add a provider for an existing notification type. |
| [`backendbase-add-notification-delivery`](../skills/backendbase-add-notification-delivery/SKILL.md) | Add queued, idempotent notification delivery. |

### Runtime and operations

| Skill | Use it for |
| --- | --- |
| [`backendbase-add-console-command`](../skills/backendbase-add-console-command/SKILL.md) | Add or change a Symfony Console command. |
| [`backendbase-add-runtime-configuration`](../skills/backendbase-add-runtime-configuration/SKILL.md) | Add typed environment-backed configuration. |
| [`backendbase-add-readiness-check`](../skills/backendbase-add-readiness-check/SKILL.md) | Add a bounded dependency readiness check. |
| [`backendbase-add-trace-aware-logging`](../skills/backendbase-add-trace-aware-logging/SKILL.md) | Add safe request or message correlation fields to logs. |
| [`backendbase-add-immutable-release-workflow`](../skills/backendbase-add-immutable-release-workflow/SKILL.md) | Add immutable release, recovery, and rollback tooling. |

### Shared input and localization

| Skill | Use it for |
| --- | --- |
| [`backendbase-add-object-mapped-input`](../skills/backendbase-add-object-mapped-input/SKILL.md) | Map validated boundary data into a typed input object. |
| [`backendbase-add-shared-primitive`](../skills/backendbase-add-shared-primitive/SKILL.md) | Add a stable value shared by several contexts. |
| [`backendbase-add-locale`](../skills/backendbase-add-locale/SKILL.md) | Add a supported locale and complete dictionary. |
| [`backendbase-add-translation`](../skills/backendbase-add-translation/SKILL.md) | Add or change local translation keys. |
| [`backendbase-sync-tolgee-i18n`](../skills/backendbase-sync-tolgee-i18n/SKILL.md) | Run an explicitly requested Tolgee synchronization. |

## Snapshot policy

The platform files and numbered HTML guides describe the current source. Their latest consistency review was on 23 September 2026. Dated assessments retain their original verification scope; generated quality metrics use the latest local test artifacts.

Treat design rules as requirements. Recheck statements marked as current state before later implementation work.

Basis: `resources/docs/0-project.html` through `resources/docs/16-shared-primitives-and-object-mapping.html`.

## Additional Rules
- Use clear subject/verb/object constructions. Do not use cleft sentences, contrastive appositives, appended-glosses, or trailing clauses.
- Assume I may edit documents myself. Especially markdown documents.
- When writing markdown documents, don't include references to conversations or threads a reader would not know about.
- Tautological tests considered harmful
