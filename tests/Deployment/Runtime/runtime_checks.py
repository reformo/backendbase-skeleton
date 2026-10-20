"""Exercise the production release through its actual deployment script and HTTP boundary."""

import json
import re
import shlex
import time
import urllib.error
import urllib.request
import uuid



def application(services, command, **options):
    return services.run("application", ["bash", "-c", "set -ae; source /run/backendbase/runtime.env; set +a; " + command], **options)


def sql(services, statement):
    return services.run(services.resources.get("database", "mysql"), ["sh", "-c", 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -N -B deployment'],
                        input_text=statement).stdout.strip()


def request(base, path, method="GET", body=None, token=None, key=None, api_key=None, decode_json=True):
    headers = {"Content-Type": "application/json", "Accept-Language": "en", "The-Timezone-IANA": "UTC"}
    if method in ["POST", "PUT", "PATCH", "DELETE"]:
        headers["Idempotency-Key"] = key or str(uuid.uuid4())
    if token:
        headers["Authorization"] = "Bearer " + token
    if api_key:
        headers["Backendbase-Api-Key"] = api_key
    data = None if body is None else json.dumps(body).encode()
    started = time.monotonic()
    try:
        response = urllib.request.urlopen(urllib.request.Request(base + path, data, headers, method=method), timeout=20)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        payload = response.read()
        if decode_json and payload and payload.lstrip().startswith((b"{", b"[")):
            try:
                payload = json.loads(payload)
            except json.JSONDecodeError as error:
                diagnostic = re.sub(r"eyJ[A-Za-z0-9._-]+", "[token]", payload.decode(errors="replace"))
                raise AssertionError("Invalid HTTP JSON on " + path + ": " + diagnostic[:700]) from error
        return response.status, payload or None, time.monotonic() - started


def expect(response, status):
    if response[0] != status:
        raise AssertionError(f"HTTP {response[0]}, expected {status}: {str(response[1])[:500]}")
    return response[1]


def deployment(services, archive, expected=0):
    command = "bash /input/deploy-release.sh /input/release/" + shlex.quote(archive.name)
    command += " /input/release/" + shlex.quote(archive.name + ".sha256") + " .env.production --fresh-install"
    result = application(services, command, timeout=180, check=False)
    if (result.returncode == 0) != (expected == 0):
        raise AssertionError("Deployment result mismatch: " + result.stdout[:3000] + result.stderr[-1500:])
    return result


def reset_installation(services):
    application(services, "supervisorctl -c /fixture/supervisord.conf stop all >/dev/null; "
                "rm -rf /srv/backendbase/releases /srv/backendbase/state /srv/backendbase/webroot; "
                "rm -f /run/rehearsal/fail-backup /run/rehearsal/fail-activation /run/rehearsal/fail-migration")
    sql(services, "DROP DATABASE deployment; CREATE DATABASE deployment CHARACTER SET utf8mb4;")


def installation_checks(services, archive, base):
    application(services, "touch /run/rehearsal/fail-backup")
    result = deployment(services, archive, expected=1)
    assert "Injected backup failure" in result.stderr, result.stdout[:3000] + result.stderr[-1500:]
    assert sql(services, "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='deployment' AND table_name!='doctrine_migration_versions'") == "0"
    application(services, "test ! -L /srv/backendbase/webroot/api")
    reset_installation(services)
    application(services, "touch /run/rehearsal/fail-migration")
    result = deployment(services, archive, expected=1)
    assert "Migration failed" in result.stdout + result.stderr, result.stdout[:3000] + result.stderr[-1500:]
    assert sql(services, "SELECT COUNT(*) FROM integration_event_outbox") == "0"
    application(services, 'test ! -L /srv/backendbase/webroot/api; test -n "$(find /srv/backendbase/state/recovery -name MIGRATION_FAILED)"')
    reset_installation(services)
    application(services, "touch /run/rehearsal/fail-activation")
    result = deployment(services, archive, expected=1)
    assert "No release is active" in result.stderr, result.stdout[:3000] + result.stderr[-1500:]
    application(services, "test ! -L /srv/backendbase/webroot/api")
    assert int(sql(services, "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='deployment'")) > 10
    reset_installation(services)
    deployment(services, archive)
    application(services, "test -f /srv/backendbase/state/active-release.json; "
                "cd /srv/backendbase/webroot/api; bin/doctrine orm:validate-schema --no-interaction", timeout=60)
    assert request(base, "/user-api/_status/ready")[0] == 503
    application(services, "rm /srv/backendbase/shared/maintenance")
    expect(request(base, "/user-api/_status/ready"), 200)
    result = application(services, "bash /srv/backendbase/webroot/api/bin/deployment/rollback-release.sh", check=False)
    assert result.returncode != 0 and "does not permit application rollback" in result.stderr
    return {"backupFailureBeforeMigration": "passed", "partialMigrationRecoveryEvidence": "passed", "activationFailureKeepsSchemaWithoutActiveRelease": "passed",
            "freshInstallationAndSchema": "passed", "maintenanceGate": "passed", "unsafeRollbackRejected": "passed"}
