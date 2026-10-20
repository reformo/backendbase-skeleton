"""Production HTTP, worker, and bounded concurrent workload checks."""

from concurrent.futures import ThreadPoolExecutor
import base64
import secrets
import statistics
import time
import uuid

from environment import wait_until
from runtime_checks import application, expect, request, sql

EXAMPLES = "/example-api/example-types/user/groups/rehearsal/examples"


def login(base, password, identifier="user@example.test", api="user-api", key=None):
    result = expect(request(base, "/" + api + "/auth/login", "POST",
                            {"identifier": identifier, "password": password}, key=key), 200)
    return result["session"]["accessToken"]


def identity_checks(services, base, values):
    password = values["FIXTURE_MYSQL_PASSWORD"]
    body = {"email": "user@example.test", "mobile": "+12025550123", "password": password}
    key = str(uuid.uuid4())
    first = expect(request(base, "/user-api/auth/register", "POST", body, key=key), 201)
    replay = expect(request(base, "/user-api/auth/register", "POST", body, key=key), 201)
    assert first == replay and sql(services, "SELECT COUNT(*) FROM account") == "1"
    token = login(base, password)
    expect(request(base, "/user-api/identity", token=token), 200)
    expect(request(base, "/system-admin-api/identity", token=token), 401)
    expect(request(base, "/user-api/identity/contact-verifications", "POST", {"contactType": "email", "verificationContextToken": base64.urlsafe_b64encode(secrets.token_bytes(32)).decode().rstrip("=")}, token=token), 200)
    mail = "http://127.0.0.1:" + str(services.port("mail", 8025))
    wait_until(lambda: request(mail, "/api/v2/messages")[1]["total"] == 1, "local SMTP delivery")
    application(services, "cd /srv/backendbase/webroot/api; bin/backendbase database:seed-admin --apply --password-file=/input/password")
    admin = login(base, password, "mehmet.korkmaz@reformo.net", "system-admin-api")
    expect(request(base, "/system-admin-api/identity", token=admin), 200)
    expect(request(base, "/user-api/identity", token=admin), 401)
    return token, admin, {"registrationReplay": "passed", "productionUserAndAdminAuthentication": "passed",
                          "realmSeparation": "passed", "smtpDeliveryToLocalSink": "passed"}


def worker_checks(services):
    application(services, "cd /srv/backendbase/webroot/api; php /input/queue-greeting.php")
    wait_until(lambda: sql(services, "SELECT COUNT(*) FROM integration_event_inbox WHERE processed_at IS NOT NULL") == "1",
               "greeting inbox completion", timeout=45)
    wait_until(lambda: sql(services, "SELECT COUNT(*) FROM integration_event_outbox WHERE published_at IS NULL") == "0", "outbox publication completion", timeout=45)
    for name in ["consumer", "relay"]:
        previous = application(services, "supervisorctl -c /fixture/supervisord.conf pid " + name).stdout.strip()
        application(services, "kill -KILL -- -" + previous)
        wait_until(lambda: application(services, "supervisorctl -c /fixture/supervisord.conf pid " + name).stdout.strip() not in [previous, "0"],
                   name + " restart")
    application(services, "cd /srv/backendbase/webroot/api; php /input/queue-greeting.php")
    try:
        wait_until(lambda: sql(services, "SELECT COUNT(*) FROM integration_event_inbox WHERE processed_at IS NOT NULL") == "2",
                   "delivery after worker restart", timeout=45)
    except RuntimeError as error:
        detail = sql(services, "SELECT event_name,COUNT(*),SUM(processed_at IS NOT NULL) FROM integration_event_inbox GROUP BY event_name; SELECT event_name,COUNT(*),SUM(published_at IS NOT NULL),SUM(attempts) FROM integration_event_outbox GROUP BY event_name")
        raise RuntimeError("Worker recovery state: " + detail) from error
    wait_until(lambda: sql(services, "SELECT COUNT(*) FROM integration_event_outbox WHERE published_at IS NULL") == "0", "publication after worker restart", timeout=45)
    output = application(services, "cd /srv/backendbase/webroot/api; bin/backendbase outbox:status").stdout
    assert "Pending: 0." in output and "Retried: 0." in output
    return {"productionOutboxRabbitMqInbox": "passed", "relayAndConsumerCrashRestart": "passed"}


def workload_checks(services, base, values, token):
    api_key = values["EXAMPLE_API_KEY"]
    sql(services, "SET SESSION cte_max_recursion_depth=10001; "
                  "INSERT INTO example_table (id,type,lookup_group,lookup_key,lookup_value,details,created_at) "
                  "WITH RECURSIVE numbers AS (SELECT 1 AS number UNION ALL SELECT number+1 FROM numbers WHERE number<10000) "
                  "SELECT UUID_TO_BIN(UUID(),0),'user',CONCAT('fixture-',MOD(number,100)),CONCAT('fixture-',number),'synthetic',JSON_OBJECT(),NOW(6) FROM numbers")
    waits_before = int(sql(services, "SELECT VARIABLE_VALUE FROM performance_schema.global_status WHERE VARIABLE_NAME='Innodb_row_lock_waits'"))
    def operation(index):
        if index % 4 == 0:
            response = request(base, EXAMPLES, "POST", {"lookupKey": "load-" + str(index), "lookupValue": "bounded load"}, token, api_key=api_key)
            expect(response, 204)
            return response[2]
        if index % 4 == 1:
            response = request(base, EXAMPLES + "?pageSize=25&page=1", token=token, api_key=api_key)
            expect(response, 200)
            return response[2]
        if index % 4 == 2:
            response = request(base, "/user-api/identity", token=token)
            expect(response, 200)
            return response[2]
        response = request(base, "/user-api/auth/login", "POST", {"identifier": "user@example.test", "password": values["FIXTURE_MYSQL_PASSWORD"]})
        expect(response, 200)
        return response[2]
    started = time.monotonic()
    with ThreadPoolExecutor(max_workers=8) as executor:
        timings = list(executor.map(operation, range(200)))
    elapsed = time.monotonic() - started
    assert sql(services, "SELECT COUNT(*) FROM example_table WHERE lookup_group='rehearsal'") == "50"
    durations = sorted(timings)
    waits_after = int(sql(services, "SELECT VARIABLE_VALUE FROM performance_schema.global_status WHERE VARIABLE_NAME='Innodb_row_lock_waits'"))
    plan = sql(services, "EXPLAIN SELECT id FROM example_table WHERE type='user' AND type_target_id IS NULL AND lookup_group='rehearsal' AND deleted_at IS NULL ORDER BY created_at,id LIMIT 25").split("\t")
    assert plan[6] == "example_group_entries_idx", "The pagination query did not select its ordering index."
    return {"boundedLocalWorkload": {"requests": 200, "concurrency": 8, "unexpectedResponses": 0,
            "writes": 50, "syntheticRows": 10000, "rowLockWaits": waits_after - waits_before,
            "paginationIndex": plan[6], "durationSeconds": round(elapsed, 3), "requestsPerSecond": round(200 / elapsed, 2),
            "medianMilliseconds": round(statistics.median(durations) * 1000, 2),
            "p95Milliseconds": round(durations[189] * 1000, 2), "p99Milliseconds": round(durations[197] * 1000, 2)}}


def interrupted_replay_checks(services, base, values, token):
    # A test-only trigger stops response persistence after the business transaction commits.
    sql(services, "CREATE TRIGGER rehearsal_response_pause BEFORE UPDATE ON http_request_replay "
                  "FOR EACH ROW SET @rehearsal_pause = SLEEP(15)")
    key = str(uuid.uuid4())
    body = {"lookupKey": "interrupted", "lookupValue": "committed before process interruption"}
    with ThreadPoolExecutor(max_workers=1) as executor:
        pending = executor.submit(request, base, EXAMPLES, "POST", body, token, key, values["EXAMPLE_API_KEY"])
        wait_until(lambda: sql(services, "SELECT COUNT(*) FROM example_table WHERE lookup_key='interrupted'") == "1",
                   "committed write before response persistence")
        process = application(services, "supervisorctl -c /fixture/supervisord.conf pid fpm").stdout.strip()
        paused = "SELECT ID FROM information_schema.PROCESSLIST WHERE DB='deployment' AND STATE='User sleep' AND COMMAND='Query'"
        wait_until(lambda: sql(services, paused).isdigit(), "paused response transaction", timeout=5)
        connection = sql(services, paused)
        assert connection.isdigit(), "The paused response transaction was not found."
        application(services, "kill -KILL -- -" + process)
        sql(services, "KILL CONNECTION " + connection)
        try:
            result = pending.result(timeout=20)
            assert result[0] >= 500
        except (ConnectionError, OSError):
            pass
    sql(services, "DROP TRIGGER rehearsal_response_pause")
    wait_until(lambda: request(base, "/user-api/_status/ready")[0] == 200, "PHP-FPM restart")
    expect(request(base, EXAMPLES, "POST", body, token, key, values["EXAMPLE_API_KEY"]), 409)
    assert sql(services, "SELECT COUNT(*) FROM example_table WHERE lookup_key='interrupted'") == "1"
    assert int(sql(services, "SELECT COUNT(*) FROM http_request_replay WHERE response IS NULL")) >= 1
    return {"processAndResponseTransactionInterruptedAfterCommit": "passed", "uncertainReplayBlocksDuplicateEffect": "passed"}


def cold_start_checks(services, base, values, token):
    failures = 0
    for attempt in range(3):
        application(services, "rm -rf /srv/backendbase/webroot/api/var/cache/example-api")
        with ThreadPoolExecutor(max_workers=8) as executor:
            futures = [executor.submit(request, base, EXAMPLES + "?pageSize=25&page=1", token=token,
                                       api_key=values["EXAMPLE_API_KEY"]) for _ in range(16)]
            for future in futures:
                try:
                    response = future.result()
                    failures += response[0] != 200
                except AssertionError:
                    failures += 1
    logs = application(services, "find /run/rehearsal /srv/backendbase/webroot/api/var -type f -name '*.log' -exec cat {} +").stdout
    return {"coldStartConcurrentRequests": {"requests": 48, "failures": failures,
            "cacheDirectoryRaceObserved": "mkdir(): File exists" in logs}}
