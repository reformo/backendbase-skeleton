# Backendbase immutable release workflow pattern

This reference generates a release workflow. It does not authorize a live build upload, deployment, database migration, process restart, or rollback.

## Target discovery

1. Read all applicable `AGENTS.md` files.
2. Inspect `composer.json`, PSR-4 roots, quality scripts, generated API or other contracts, migration namespace and CLI, health endpoints, cache commands, worker processes, CI provider, deployment files, and tests.
3. Identify the current server layout, artifact store, secret delivery, backup system, process manager, readiness URL, and release authority.
4. Determine whether the previous application can run against each proposed expanded schema.
5. Find existing deployment state and preserve its ownership.

Do not copy the Backendbase deploy root, symbolic-link paths, API names, readiness URL, environment file names, migration class, fixture revisions, service account, or secret values.

## Role to target mapping

| Role | Backendbase reference | Target decision |
| --- | --- | --- |
| Release gates | CI release workflow | Reuse target tests, analysis, formatting, and generated-contract checks. |
| Artifact build | `build-release.sh` | Package one exact reviewed revision and production dependencies. |
| Release manifest | `release-manifest.json` | Bind revision, dependency lock, schema target, and rollback policy. |
| Integrity | archive SHA-256 file | Verify before extraction. |
| Guarded activation | `deploy-release.sh` | Lock, validate, migrate, clear cache, switch, activate, check. |
| Backup hook | `pre-migrate` | Target platform must create recoverable state. |
| Activation hook | `activate-release` | Reload application and restart workers. |
| Recovery evidence | state recovery directory | Preserve plans, output, state, and markers. |
| Application rollback | `rollback-release.sh` | Return only to the recorded compatible release. |

## Release manifest

Use a small machine-readable manifest with these roles:

| Field | Meaning |
| --- | --- |
| revision | exact immutable source revision |
| dependencyLockDigest | digest of the committed dependency lock |
| migrationTarget | exact reviewed schema target |
| applicationRollbackSafe | whether the previous application can use the resulting schema |

Backendbase uses full Git commit SHA values and SHA-256. Adapt source revision and digest mechanisms only when the target has an equivalent immutable identity.

## Build workflow

1. Require an exact reviewed revision.
2. Verify the checked-out revision matches it.
3. Reject staged or unstaged changes.
4. Verify release policy and the latest migration target.
5. Export tracked source from the exact revision into a temporary directory.
6. Install production dependencies with an authoritative autoloader.
7. Generate and validate public contracts.
8. Write the release manifest.
9. Create the archive and separate checksum file.
10. Upload only the archive and checksum through the selected CI artifact mechanism.

Small revision guard:

```sh
[[ "$revision" =~ ^[0-9a-f]{40}$ ]] || exit 1
[[ "$(git rev-parse HEAD)" == "$revision" ]] || exit 1
git diff --quiet || exit 1
git diff --cached --quiet || exit 1
```

Do not include `.env`, live secrets, development dependencies, runtime logs, caches, or a server working tree in the artifact.

## Guarded activation

The target deployment script should perform this order:

1. Validate arguments, absolute scoped paths, required commands, and the deployment lock.
2. Refuse to replace an unmanaged directory or link.
3. Resolve the archive and checksum to exact existing files.
4. Verify checksum format and content.
5. Reject absolute paths and parent traversal in the archive.
6. Extract into a new staging directory under the controlled release root.
7. Validate manifest fields, dependency-lock digest, production dependencies, executables, and absence of environment files.
8. Link or inject the protected target environment outside the archive.
9. Verify platform requirements.
10. Record current migration status and reviewed dry-run SQL.
11. Run the required backup hook.
12. Apply only the exact manifest migration target.
13. Record migration output and failure markers.
14. Clear generated caches and verify schema status.
15. Record the previous release.
16. Atomically switch the active release pointer.
17. Run the activation hook to reload application processes and workers.
18. Pass bounded readiness checks.
19. Atomically write active-release state.

If activation or readiness fails after migration, restore the previous application when compatible. The expanded schema remains. Do not silently run down migrations.

## Hook contracts

### Pre-migration backup

Inputs should identify the new release, previous release, exact migration target, and recovery directory. The hook returns success only after a usable backup or point-in-time recovery checkpoint exists. It writes a safe reference into recovery state, not credentials.

### Release activation

Inputs should identify active and previous release paths. The hook reloads the application and restarts background workers from the active release. It returns success only when processes accept work.

The repository cannot provide portable implementations because backup and process managers are server-specific. Do not invent them without target details.

## Recovery evidence

Preserve:

- previous release path;
- release manifest;
- migration status before execution;
- dry-run migration plan;
- migration output;
- backup reference;
- migration, activation, and success markers.

Use this evidence for reviewed forward repair, approved down migration, or restore. MySQL data-definition changes can commit independently.

## Restricted application rollback

1. Acquire the same deployment lock.
2. Require a managed active release and active-release state.
3. Require `applicationRollbackSafe`.
4. Resolve only the recorded previous release under the controlled release directory.
5. Optionally require the operator to repeat its exact revision.
6. Switch the application pointer atomically.
7. Run activation and bounded readiness.
8. Keep the rollback only after both pass.
9. Restore the original release if rollback activation fails.

Rollback does not change schema. Never accept an arbitrary release path or revision.

## CI workflow

The release job should:

1. check out the reviewed revision with enough history to identify it;
2. set up the exact supported PHP runtime and extensions;
3. validate and audit dependency metadata;
4. install dependencies;
5. run tests, PHPStan level 8, PHPCS, and generated-contract checks;
6. call the deterministic artifact builder;
7. upload archive and checksum with finite retention;
8. use read-only source permissions unless more are required.

Keep CI action versions and platform settings target-specific.

## Test strategy

Use an isolated temporary deployment root and fake external commands. Test at least:

- successful activation and state recording;
- backup hook execution;
- migration failure before activation;
- activation or readiness failure restoring the previous application;
- restricted rollback;
- invalid checksum;
- unsafe archive paths;
- missing manifest, environment, hook, dependency, or executable;
- unmanaged active path;
- deployment lock conflict;
- rollback-unsafe schema policy.

Tests must never use a production deploy root or live database.

## Verified Backendbase invariants and limits

- Build input is a full 40-character commit SHA.
- The release policy target must equal the latest migration class.
- The archive includes production Composer dependencies and generated OpenAPI.
- The manifest binds revision, `composer.lock` digest, migration target, and rollback policy.
- Deployment requires checksum validation, safe extraction, backup and activation hooks, migration dry run, cache clear, atomic link switch, and readiness.
- Online deployment rejects a manifest whose application rollback policy is false.
- Application rollback accepts only the recorded previous release and leaves schema unchanged.
- Current scripts provide no server-specific backup hook, process manager, scheduler, or alert implementation.

## Authorization boundary

Generating and testing workflow files in an isolated directory is allowed within the code task. Require explicit release authority before:

- uploading an artifact outside the repository;
- connecting to a server;
- changing a release link;
- reading or linking protected environment files;
- running a backup hook;
- applying or reversing a migration;
- restarting application or worker processes;
- executing readiness against a live environment;
- rolling back a release.

## Verification

Adapt generated-contract commands to the target, then run:

```sh
bash tests/Deployment/deployment-scripts.sh
composer test
composer phpstan
composer cs-check
composer validate --strict --no-check-publish
```

Also run the target's contract generation and validation commands. A real artifact build can require network access for production dependencies and must use an exact clean revision.

## Completion report

Report:

- workflow and script files;
- artifact contents and exclusions;
- manifest fields and rollback policy;
- migration and recovery behavior;
- hook contracts still needing platform implementation;
- isolated tests and quality checks;
- live build, upload, deploy, migration, restart, readiness, and rollback actions not performed;
- operator decisions required before use.

## Provenance

Verified on 2026-08-25 from:

- `.github/workflows/release-artifact.yml`
- `.github/workflows/quality-gates.yml`
- `bin/deployment/build-release.sh`
- `bin/deployment/deploy-release.sh`
- `bin/deployment/rollback-release.sh`
- `bin/deployment/update-test.sh`
- `bin/deployment/update-prod.sh`
- `bin/deployment/lib.sh`
- `deployment/release.json`
- `deployment/README.md`
- `tests/Deployment/deployment-scripts.sh`
- `resources/platform/18-deployment.md`
- `resources/platform/13-queue-runtime.md`
- `resources/docs/12-deployment-and-operations.html`
