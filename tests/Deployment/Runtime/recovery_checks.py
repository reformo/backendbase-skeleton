"""Recover only fixture-owned MySQL data and Redis namespaces."""

import base64
import hashlib
import json
import uuid

from behavior_checks import login
from environment import wait_until
from runtime_checks import application, expect, request, sql


def dump(services, host, data_only=False):
    command = 'MYSQL_PWD="$FIXTURE_MYSQL_PASSWORD" mysqldump --host=' + host
    command += " --user=root --single-transaction --routines --events --triggers --hex-blob --no-tablespaces"
    command += " --set-gtid-purged=OFF --column-statistics=0 --skip-comments --skip-add-locks --skip-disable-keys --order-by-primary deployment"
    if data_only:
        command += " --no-create-info"
    return application(services, command, timeout=90).stdout


def backup_restore_checks(services, base, values):
    password = values["FIXTURE_MYSQL_PASSWORD"]
    key = str(uuid.uuid4())
    token = login(base, password, key=key)
    application(services, "touch /srv/backendbase/shared/maintenance; supervisorctl -c /fixture/supervisord.conf stop all")
    before = dump(services, "mysql")
    expected_data = dump(services, "mysql", data_only=True)
    metadata_query = "SELECT TABLE_NAME,COLUMN_NAME,ORDINAL_POSITION,COLUMN_TYPE,IS_NULLABLE,COALESCE(COLUMN_DEFAULT,'<NULL>'),EXTRA,COALESCE(COLLATION_NAME,''),GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='deployment' ORDER BY TABLE_NAME,ORDINAL_POSITION; SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME,COALESCE(COLLATION,''),COALESCE(SUB_PART,0),INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='deployment' ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX"
    expected_metadata = sql(services, metadata_query)
    assert "http_request_replay" in before and "integration_event_outbox" in before
    services.start("mysql-restore", "mysql", environment={"MYSQL_ROOT_PASSWORD": password,
                   "MYSQL_DATABASE": "deployment"}, memory="1g", tmpfs=["/var/lib/mysql:rw,size=768m"])
    wait_until(lambda: services.run("mysql-restore", ["sh", "-c", 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -N -e "SELECT 1"'], check=False).returncode == 0,
               "restore MySQL readiness")
    services.run("mysql-restore", ["sh", "-c", 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot deployment'], input_text=before, timeout=90)
    after = dump(services, "mysql-restore", data_only=True)
    assert hashlib.sha256(expected_data.encode()).digest() == hashlib.sha256(after.encode()).digest(), "Restored row contents differ."
    services.resources["database"] = "mysql-restore"
    assert sql(services, metadata_query) == expected_metadata, "Restored columns or indexes differ."
    services.stop("mysql")
    application(services, "sed -i 's/@mysql:3306/@mysql-restore:3306/' /run/backendbase/runtime.env; "
                "supervisorctl -c /fixture/supervisord.conf start all; rm /srv/backendbase/shared/maintenance")
    wait_until(lambda: request(base, "/user-api/_status/ready")[0] == 200, "restored application readiness")
    assert login(base, password, key=key) == token
    expect(request(base, "/user-api/identity", token=token), 200)
    assert sql(services, "SELECT COUNT(*) FROM example_table WHERE lookup_group='rehearsal'") == "51"
    assert sql(services, "SELECT COUNT(*) FROM `user` u LEFT JOIN account a ON a.id=u.account_id WHERE a.id IS NULL") == "0"
    assert sql(services, "SELECT COUNT(*) FROM `admin` u LEFT JOIN account a ON a.id=u.account_id WHERE a.id IS NULL") == "0"
    application(services, "cd /srv/backendbase/webroot/api; bin/doctrine orm:validate-schema --no-interaction")
    return {"populatedBackupRestoredToIndependentMySql": "passed", "completeRowContentsColumnsAndIndexesMatch": "passed",
            "encryptedResponseReplaysAfterColdDatabaseSwitch": "passed", "restoredSchemaAndReferences": "passed"}


def redis(services, name, arguments):
    return services.run(name, ["redis-cli", "--no-auth-warning", "-a", services.resources["redis-password"], *arguments]).stdout.strip()


def account_id(token):
    payload = token.split(".")[1]
    return json.loads(base64.urlsafe_b64decode(payload + "=" * (-len(payload) % 4)))["accountId"]


def invalidate(services, account, realm):
    keys = redis(services, "replica", ["--scan", "--pattern", "identity:{" + realm + ":" + account + "}:*"]).splitlines()
    allowlists = [key for key in keys if ":jti:" in key or ":sid:" in key]
    for offset in range(0, len(allowlists), 100):
        redis(services, "replica", ["DEL", *allowlists[offset:offset + 100]])


def redis_recovery_checks(services, base, values):
    password = values["FIXTURE_MYSQL_PASSWORD"]
    services.resources["redis-password"] = password
    user = login(base, password)
    admin = login(base, password, "mehmet.korkmaz@reformo.net", "system-admin-api")
    closed = {"email": "closed@example.test", "mobile": "+12025550124", "password": password}
    expect(request(base, "/user-api/auth/register", "POST", closed), 201)
    closed_token = login(base, password, "closed@example.test")
    assert int(redis(services, "redis", ["WAIT", "1", "5000"])) == 1
    redis(services, "replica", ["REPLICAOF", "NO", "ONE"])
    expect(request(base, "/user-api/identity/session", "DELETE", token=user), 200)
    expect(request(base, "/system-admin-api/identity/session", "DELETE", token=admin), 200)
    expect(request(base, "/user-api/identity/account", "DELETE", token=closed_token), 200)
    expect(request(base, "/user-api/identity", token=user), 401)
    expect(request(base, "/system-admin-api/identity", token=admin), 401)
    expect(request(base, "/user-api/identity", token=closed_token), 401)
    services.stop("redis")
    outage = request(base, "/user-api/identity", token=user, decode_json=False)
    assert outage[0] == 503
    try:
        outage_payload = json.loads(outage[1])
        valid_outage = outage_payload.get("code") == "identity/unavailable"
    except json.JSONDecodeError:
        valid_outage = False
    application(services, "touch /srv/backendbase/shared/maintenance; supervisorctl -c /fixture/supervisord.conf stop fpm; "
                "sed -i 's/BACKENDBASE_REDIS_HOST=redis/BACKENDBASE_REDIS_HOST=replica/' /run/backendbase/runtime.env")
    for token, realm in [(user, "USER"), (admin, "ADMIN")]:
        assert redis(services, "replica", ["--scan", "--pattern", "identity:{" + realm + ":" + account_id(token) + "}:jti:*"])
        invalidate(services, account_id(token), realm)
    # Keep the stale closed Account allowlists to test current MySQL authority independently.
    assert redis(services, "replica", ["--scan", "--pattern", "identity:{USER:" + account_id(closed_token) + "}:jti:*"])
    application(services, "supervisorctl -c /fixture/supervisord.conf start fpm; rm /srv/backendbase/shared/maintenance")
    expect(request(base, "/user-api/identity", token=user), 401)
    expect(request(base, "/system-admin-api/identity", token=admin), 401)
    expect(request(base, "/user-api/identity", token=closed_token), 401)
    fresh = login(base, password)
    expect(request(base, "/user-api/identity", token=fresh), 200)
    fresh_admin = login(base, password, "mehmet.korkmaz@reformo.net", "system-admin-api")
    expect(request(base, "/system-admin-api/identity", token=fresh_admin), 200)
    expect(request(base, "/user-api/auth/login", "POST", {"identifier": "closed@example.test", "password": password}), 401)
    return {"redisOutageReturns503": "passed", "redisOutageHasOneValidJsonProblem": valid_outage, "staleReplicaHasAcknowledgedRevocationGap": "passed",
            "controlledInvalidationOfBothRealms": "passed", "mysqlClosureRejectsRestoredAllowlists": "passed",
            "freshLoginAfterManualPromotion": "passed"}


def observation_checks(services, values):
    before = sql(services, "SELECT COUNT(*) FROM http_request_replay WHERE response IS NULL")
    application(services, "cd /srv/backendbase/webroot/api; bin/backendbase integration-messages:cleanup --retention-days=30 --limit=100; bin/backendbase outbox:status")
    assert sql(services, "SELECT COUNT(*) FROM http_request_replay WHERE response IS NULL") == before
    logs = application(services, "find /run/rehearsal /srv/backendbase/webroot/api/var -type f -name '*.log' -exec cat {} +").stdout
    sensitive = [value for key, value in values.items() if key.endswith("PASSWORD") or key.endswith("_KEY")]
    assert not any(value in logs for value in sensitive), "A generated secret appeared in diagnostics."
    return {"outboxStatusAndRetentionCommand": "passed", "unresolvedClaimsRetained": "passed",
            "generatedSecretsAbsentFromDiagnosticLogs": "passed"}
