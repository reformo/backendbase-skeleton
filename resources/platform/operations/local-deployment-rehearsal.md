# Local Deployment Rehearsal

Use `tests/Deployment/local-runtime.py` to exercise the current workspace with disposable local services.
The runner does not read the checkout's `.env` file or use an existing deployment target.

## Requirements

- Use Python 3, Docker, PHP, Composer, and the repository's build dependencies.
- Keep the cached PHP base image `backendbase-coverage-php:8.5.10` available.
- Prepare the service images listed in `tests/Deployment/Runtime/runtime_services.py`.
- Allow approximately 4 GB of additional Docker memory.
- Authorize local Docker creation, temporary database writes, and removal of those resources.

The PHP base image supplies PHP 8.5 and its extensions.
The fixture Dockerfile adds PHP-FPM process supervision and the MySQL client.
Docker can reuse its build cache. An uncached image build needs access to its package repositories.
The runner resolves service image references to local image identifiers before starting containers. It does not pull service images.

Run the rehearsal from the repository root:

```bash
python3 tests/Deployment/local-runtime.py
```

The runner builds an immutable release from a temporary snapshot of the current workspace.
It excludes local environment files. The temporary snapshot does not change the checkout's Git state.
The production builder verifies dependency contents and removes development packages.
The application uses the resulting archive, its production dependencies, and its generated API files.

## Isolation and cleanup

Each run creates a unique ownership label for its containers, networks, volume, and runtime image.
The application and data services use an internal Docker network.
Nginx and the SMTP viewer also use a separate frontend network. Published ports bind to `127.0.0.1` only.
The runner verifies container labels and network membership before executing service commands.
It does not mount the Docker socket or use privileged containers.

Credentials and cryptographic keys are generated for the run. The application receives them outside the release.
Database data, Redis records, messages, mail, and object-store contents belong only to these fixtures.
All database installation, restore, and namespace invalidation operations use these owned services.

The normal command removes its temporary source, archive, credentials, services, networks, volume, and runtime image.
Cleanup also runs after a failed assertion or a handled interruption.
Existing containers, data volumes, and cached base images remain outside the cleanup scope.
A forced host termination can prevent cleanup. Identify remaining resources by the exact run ownership label before removal.

The optional positional argument accepts an existing rehearsal directory directly under the system temporary directory.
Use this only to diagnose a previously built local artifact.
A failed diagnostic run retains that archive but removes generated credentials and Docker resources.
A successful diagnostic run also removes the directory.

## Executed checks

| Area | Check |
| --- | --- |
| Installation | Run the real release script, four migrations, platform checks, and Doctrine schema validation. |
| Maintenance | Block public traffic until activation and readiness succeed. |
| Backup failure | Reject deployment before application tables are created. |
| Migration failure | Inject a table conflict after the backup. Retain committed MySQL schema changes and recovery evidence without activating a release. |
| Activation failure | Retain the migrated schema and remove the active application link. |
| Rollback | Reject application rollback when the release marks its schema unsafe. |
| HTTP composition | Register and authenticate USER and ADMIN identities through Nginx and PHP-FPM. Verify realm separation and response replay. |
| Local delivery | Send verification email to the isolated SMTP sink. Check real S3, RabbitMQ, Redis, and MySQL readiness. |
| Workers | Dispatch a command through the production container. Verify outbox publication, RabbitMQ consumption, and inbox completion before and after process-group termination. |
| Cold startup | Send 48 concurrent requests across three empty-cache starts. Record response failures separately so recovery checks can continue. |
| Concurrent workload | Use eight clients for 200 mixed login, identity, pagination, and write requests with 10,000 synthetic database rows. Record latency, response counts, and row-lock waits. |
| Query plan | Verify that the pagination query selects `example_group_entries_idx`. |
| Interrupted response | Pause replay completion after the business commit. Terminate PHP-FPM and its unfinished response transaction. Require HTTP 409 on retry and exactly one business effect. |
| Database recovery | Restore a populated logical backup into an independent MySQL container. Compare all row contents, column definitions, and indexes. Stop the original database and verify schema, references, and encrypted response replay. |
| Redis recovery | Detach a synchronized replica, revoke sessions, and close an Account. Stop the primary and check HTTP 503. Invalidate affected USER and ADMIN allowlists before reopening traffic on the promoted replica. |
| Current authority | Preserve the closed Account's stale allowlists and verify that current MySQL state still denies access. Verify fresh login for eligible accounts. |
| Operations | Check outbox status, retention commands, unresolved claim retention, and generated configuration-secret absence from diagnostic logs. |

Concurrent cache creation can emit a PHP warning after another worker creates the directory.
Shutdown handling must preserve the existing response after warnings. A Redis outage must return one valid HTTP 503 problem.
The runner records any response failures and completes independent recovery checks before cleanup.

The sanitized result is written to `artifacts/deployment-rehearsal/results.json`.
`completed: true` requires all checks to finish. `allChecksPassed` also requires zero cold-start response failures and one valid JSON problem during Redis outage.
A recorded response failure makes the command return a nonzero exit status. Cleanup status is recorded separately.
The report contains no credentials, tokens, account identifiers, release identifiers, or previous results.

## Evidence boundary

The fixture uses Supervisor for process control. It does not execute target systemd units or a target secret provider.
Its Redis promotion is explicit. The application still uses one configured Redis endpoint.
Separate Sentinel tests cover automatic elections in their own local topology.
Registration proofs are disabled in this fixture so runtime recovery checks can create identities without external SMS delivery.
Existing registration-policy tests cover proof requirements.

MySQL can finish a response update after its client exits. The interruption check also terminates that paused database connection.
This creates the uncertain interval between the committed business effect and durable response completion.

Local latency measures describe this synthetic workload and this machine.
They do not establish target capacity, a service-level objective, or a production recovery time.
Provider durability, deployment storage, target backup policy, TLS, server hooks, monitoring integration, and existing-database adoption require separate target evidence.
See [identity session recovery](identity-session-recovery.md), [managed servers](managed-servers.md), and [identity acceptance](identity-acceptance.md).
