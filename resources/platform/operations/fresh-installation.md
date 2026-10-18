# Maintenance installation on an empty database

Use this procedure only for an identified, authorized new installation.
The current consolidated migrations do not convert an existing database.
Keep `application-rollback-safe: false` in the release policy.

## Prepare the target

1. Select the reviewed release archive and its checksum.
2. Record the target, operator, maintenance interval, and recovery owner.
3. Provision a dedicated empty MySQL database.
4. Configure the shared environment file outside the release archive.
5. Keep external traffic disabled.
6. Stop workers that could write to the selected database.
7. Install executable `prepare-maintenance`, `pre-migrate`, and `activate-release` hooks under `shared/hooks`.

`prepare-maintenance` receives the release path and recovery directory.
It must verify that traffic and conflicting workers remain stopped.
Record the target database and maintenance evidence in the recovery directory.
The command does not reopen external traffic.

`pre-migrate` receives the release path, previous release path, migration target, and recovery directory.
Record the backup or recovery checkpoint before returning success.
The previous release path is empty for this procedure.

`activate-release` reloads PHP and starts the selected workers.
Configure readiness against the prepared dependencies.
Keep external traffic disabled until final acceptance.

## Install

Run the reviewed deployment tool:

```sh
bin/deployment/deploy-release.sh \
  /approved/incoming/backendbase-REVISION.tar.gz \
  /approved/incoming/backendbase-REVISION.tar.gz.sha256 \
  .env.production \
  --fresh-install
```

The command refuses an active deployment, a previous release link, or recorded active state.
Backup and activation hooks remain mandatory in this mode.
Archive, manifest, dependency-evidence, platform, migration-target, and deployment-lock checks remain active.

The database guard rejects existing tables, views, routines, and events before migration metadata is created.
Its schema-filtered `information_schema` aggregate reads intentionally inspect all selected schema objects.
These system-view checks do not require application indexes.

After migration, the command clears caches, checks migration state, activates the release, and checks readiness.
It records the original rollback policy in active state.
Normal updates still require a rollback-safe release.

## Recover failures

Keep maintenance restrictions active after every failure.
Inspect `state/recovery/<time>-<revision>` before deciding recovery.

| Failure | Required action |
| --- | --- |
| Maintenance or empty-database check | Correct target selection. Do not apply migrations. |
| Backup hook | Establish recovery evidence before another attempt. |
| Migration | Inspect committed DDL and retained migration output. Select reviewed forward repair or restore. |
| Activation or readiness | The command removes the new active link. Repair the release or restore the approved checkpoint. |

MySQL DDL can commit before a later statement fails.
Do not assume that a failed command left an empty database.
Do not drop data or change migration history merely to satisfy the guard.
The failed release directory and recovery records remain available for investigation.
Resolve that retained directory before retrying the same revision.

No previous application exists for automatic rollback.
The ordinary rollback command must not accept this rollback-unsafe state.

## Acceptance and evidence

Verify readiness, worker health, identity behavior, and recovery evidence before reopening traffic.
Use [identity acceptance](identity-acceptance.md) for target-specific performance and failover measurements.

Deployment-script fixtures verify successful activation and backup, migration, activation, readiness, and existing-state failures.
Native MySQL tests verify empty-schema acceptance and rejection of tables, views, routines, and events.
These checks do not verify server-specific hook implementations.

Existing databases require a separate [migration adoption plan](migration-consolidation.md).
