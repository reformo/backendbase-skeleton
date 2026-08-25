# Backendbase Agent Context

Use this directory as modular prompt context for Backendbase Core. Each file covers one concern and can be included independently.

## Loading rule

1. Always include `00-platform.md` and `02-architecture.md`.
2. Add only the files needed for the current task.
3. Inspect the current source before implementation. Source code can change after these summaries.
4. Use the HTML guides under `resources/docs` when a summary does not answer a required detail.

Do not include this whole directory by default. That action reduces prompt focus and repeats some context.

## Task routing

| Task | Add these files |
| --- | --- |
| Locate a change | `01-repository-map.md`, `24-feature-workflow.md` |
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

## Snapshot policy

The source guides describe the repository on 25 August 2026. Their disputed current-state claims were checked against source during this summary update.

Treat design rules as requirements. Recheck statements marked as current state before later implementation work.

Basis: `resources/docs/0-project.html` through `resources/docs/16-shared-primitives-and-object-mapping.html`.
