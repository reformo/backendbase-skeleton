# Immutable deployments

Production and stage deployments consume a release archive and its SHA-256 file. They do not use a server Git working tree.

## Build a release

Use the `Release artifact` GitHub Actions workflow. A `v*` tag or a manual reviewed revision starts the workflow.

The workflow repeats the release gates. It then runs:

```bash
bin/deployment/build-release.sh <full-commit-sha> artifacts/releases
```

The builder first installs dependencies with plugins and scripts disabled. It verifies the reviewed package-content digests before any dependency code executes. It then enables trusted build scripts to generate and validate OpenAPI. A final `composer install --no-dev` command removes development packages.

The archive contains production Composer dependencies, generated OpenAPI, the CycloneDX SBOM, the package-content manifest, and `release-manifest.json`. The manifest binds the commit, `composer.lock`, both supply-chain evidence files, the migration target, and the rollback policy.

Update `deployment/release.json` when a migration is added. Set `applicationRollbackSafe` to `true` only when the previous application can use the new schema.

## Prepare a server

Use this layout:

```text
/opt/backendbase/
├── webroot/api -> /opt/backendbase/releases/<active-revision>
├── previous -> /opt/backendbase/releases/<previous-revision>
├── releases/
├── shared/
│   ├── .env.production
│   ├── .env.stage
│   └── hooks/
│       ├── pre-migrate
│       └── activate-release
└── state/
```

Complete the first conversion during a maintenance window. Verify that the existing working tree is clean. Identify its exact full commit SHA. Move it to `releases/<full-commit-sha>`. Move the environment file into `shared` and link the release `.env` to it. Create the `webroot/api` symbolic link to that directory. The deployment command refuses to replace a directory.

Do not store environment files in release archives.

## Required hooks

Both hooks must be executable. A nonzero exit status stops deployment.

`shared/hooks/pre-migrate` receives:

1. New release path.
2. Previous release path, or an empty value.
3. Exact migration target.
4. Recovery directory.

Create a database backup or point-in-time recovery checkpoint. Write its identifier into the recovery directory. Return success only after recovery is available.

`shared/hooks/activate-release` receives:

1. Active release path.
2. Previous release path, or an empty value.

Reload PHP and restart queue workers from the active release. Return success only after the processes accept work.

## Deploy

Copy both workflow files to a server-controlled incoming directory. Then run one command:

```bash
bin/deployment/update-prod.sh \
  /path/to/backendbase-<revision>.tar.gz \
  /path/to/backendbase-<revision>.tar.gz.sha256
```

Use `update-test.sh` for stage. Override `BACKENDBASE_READINESS_URL` when the local readiness URL differs.

The command verifies the archive checksum, release manifest, Composer lock, supply-chain evidence, Composer platform, environment, hooks, and migration plan. It holds a deployment lock. It activates the release with a symbolic-link rename. It restores the previous application release when activation or readiness fails.

## Recover a migration

Each attempt writes data under `state/recovery/<time>-<revision>`:

- Previous release path.
- Release manifest.
- Migration status before execution.
- Dry-run SQL.
- Migration output.
- Failure or success marker.

Do not automatically run a down migration. MySQL can commit data-definition statements independently. Use the recorded plan and backup to select a reviewed forward repair, an approved down migration, or a restore.

## Roll back the application

Run:

```bash
bin/deployment/rollback-release.sh <allowed-previous-revision>
```

The command accepts only the previous release recorded during activation. It does not change the schema. It reloads the application and workers. It keeps the rollback only after readiness succeeds.
