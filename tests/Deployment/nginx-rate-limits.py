#!/usr/bin/env python3
"""Verify the deployment policy with one owned Nginx container and no application services."""

from concurrent.futures import ThreadPoolExecutor
from http.client import HTTPConnection
import json
import math
from pathlib import Path
import secrets
import sys
import tempfile
import time

sys.path.insert(0, str(Path(__file__).parent / "Runtime"))
from runtime_services import IMAGES, execute


configuration = """
worker_processes 2;
pid /tmp/nginx.pid;
error_log /dev/stderr warn;
events { worker_connections 1024; }
http {
    include /etc/nginx/backendbase/http-rate-limit.conf;
    log_format upstream '$uri';
    access_log off;
    server {
        listen 8080;
        include /etc/nginx/backendbase/api-rate-limit.conf;
        location / { proxy_pass http://127.0.0.1:8081; }
        location = /internal-ready {
            set $apiClientRateKey "";
            allow 172.16.0.0/12;
            allow 192.168.0.0/16;
            allow 10.0.0.0/8;
            allow 127.0.0.1;
            deny all;
            proxy_pass http://127.0.0.1:8081;
        }
    }
    server {
        listen 8082;
        include /etc/nginx/backendbase/api-rate-limit.conf;
        location / { proxy_pass http://127.0.0.1:8081; }
    }
    server {
        listen 127.0.0.1:8081;
        access_log /tmp/upstream-access.log upstream;
        default_type application/json;
        location / { return 200 '{"passed":true}'; }
        location = /user-api/verification-limit {
            default_type application/problem+json;
            add_header Retry-After 60 always;
            return 429 '{"type":"about:blank","code":"identity/verification-limited","title":"Verification limit reached","status":429,"detail":"Wait before requesting another code."}';
        }
        location = /user-api/dependency-unavailable {
            return 503 '{"status":503}';
        }
    }
}
"""


def request(port, path, method="GET", forwardedAddress="198.51.100.1"):
    connection = HTTPConnection("127.0.0.1", port, timeout=5)
    try:
        connection.request(method, path, headers={"X-Forwarded-For": forwardedAddress,
                                                 "X-Real-IP": forwardedAddress})
        response = connection.getresponse()
        return response.status, dict(response.getheaders()), response.read()
    finally:
        connection.close()


def waitForGateway(port):
    deadline = time.monotonic() + 10
    lastFailure = None
    while time.monotonic() < deadline:
        try:
            if request(port, "/internal-ready")[0] == 200:
                return
        except OSError as error:
            lastFailure = error
        time.sleep(0.1)
    raise RuntimeError("The isolated Nginx gateway did not start.") from lastFailure


def assertApplicationErrors(port):
    status, headers, body = request(port, "/user-api/verification-limit")
    assert status == 429 and headers["Retry-After"] == "60"
    assert json.loads(body)["code"] == "identity/verification-limited"
    status, headers, body = request(port, "/user-api/dependency-unavailable")
    assert status == 503 and "Retry-After" not in headers
    assert json.loads(body)["status"] == 503


def assertGatewayProblem(result, method):
    status, headers, body = result
    assert status == 429
    assert headers["Content-Type"] == "application/problem+json"
    assert headers["Retry-After"] == "1" and headers["Cache-Control"] == "no-store"
    if method == "HEAD":
        assert body == b""
        return
    problem = json.loads(body)
    assert problem == {"type": "about:blank", "code": "system/rate-limit-exceeded",
                       "title": "Too Many Requests", "status": 429,
                       "detail": "The API request limit was reached."}


def assertSharedBudget(port):
    apis = ["example-api", "user-api", "system-admin-api"]
    methods = ["GET", "POST", "PUT", "PATCH", "DELETE", "HEAD", "OPTIONS"]
    def probe(index):
        api = apis[index % len(apis)]
        method = methods[index % len(methods)]
        result = request(port, f"/{api}/rate-limit-probe/{index}", method,
                         f"198.51.100.{index % 200 + 1}")
        return api, method, result

    started = time.monotonic()
    with ThreadPoolExecutor(max_workers=32) as executor:
        results = list(executor.map(probe, range(240)))
    duration = time.monotonic() - started
    assert all(result[0] in [200, 429] for _, _, result in results)
    accepted = sum(result[0] == 200 for _, _, result in results)
    assert 61 <= accepted <= 61 + math.ceil(30 * duration), (accepted, duration)
    rejected = [(api, method, result) for api, method, result in results if result[0] == 429]
    assert {api for api, _, _ in rejected} == set(apis)
    assert {method for _, method, _ in rejected} == set(methods)
    for _, method, result in rejected:
        assertGatewayProblem(result, method)
    return accepted


def assertSeparateClient(container):
    response = execute(["docker", "exec", container, "wget", "-q", "-O", "-",
                        "http://127.0.0.1:8080/user-api/client-probe"])
    assert json.loads(response.stdout) == {"passed": True}


def assertReadinessAndRecovery(port, otherPort):
    with ThreadPoolExecutor(max_workers=16) as executor:
        responses = list(executor.map(lambda _: request(port, "/internal-ready"), range(32)))
    assert all(response[0] == 200 for response in responses)
    with ThreadPoolExecutor(max_workers=16) as executor:
        responses = list(executor.map(lambda _: request(otherPort, "/internal-ready"), range(32)))
    assert any(response[0] == 429 for response in responses), "The path alone must not bypass the quota."
    time.sleep(1)
    assert request(port, "/user-api/recovery-probe")[0] == 200


def removeContainer(container, token):
    inspected = execute(["docker", "inspect", container], check=False)
    if inspected.returncode:
        return
    details = json.loads(inspected.stdout)[0]
    if details["Config"]["Labels"].get("backendbase.nginx-rate-limits") != token:
        raise RuntimeError("Refusing to remove an unowned container.")
    execute(["docker", "rm", "--force", container])


def runChecks(root, directory, container, token):
    image = execute(["docker", "image", "inspect", IMAGES["nginx"], "--format", "{{.Id}}"])
    (directory / "nginx.conf").write_text(configuration)
    directory.chmod(0o755)
    execute(["docker", "run", "--detach", "--pull", "never", "--name", container,
             "--label", "backendbase.nginx-rate-limits=" + token, "--user", "101:101",
             "--read-only", "--tmpfs", "/tmp:rw,size=16m,mode=1777",
             "--tmpfs", "/var/cache/nginx:rw,size=16m,mode=1777", "--memory", "128m",
             "--add-host", "application:127.0.0.1",
             "--publish", "127.0.0.1::8080", "--publish", "127.0.0.1::8082", "--entrypoint", "nginx",
             "--mount", "type=bind,src=" + str(directory) + ",dst=/test,readonly",
             "--mount", "type=bind,src=" + str(root / "resources/testing/deployment-runtime") + ",dst=/fixture,readonly",
             "--mount", "type=bind,src=" + str(root / "resources/deployment/nginx") + ",dst=/etc/nginx/backendbase,readonly",
             image.stdout.strip(), "-c", "/test/nginx.conf", "-g", "daemon off;"])
    inspected = json.loads(execute(["docker", "inspect", container]).stdout)[0]
    binding = inspected["NetworkSettings"]["Ports"]["8080/tcp"][0]
    assert binding["HostIp"] == "127.0.0.1"
    port = int(binding["HostPort"])
    otherBinding = inspected["NetworkSettings"]["Ports"]["8082/tcp"][0]
    assert otherBinding["HostIp"] == "127.0.0.1"
    waitForGateway(port)
    execute(["docker", "exec", container, "nginx", "-t", "-c", "/test/nginx.conf"])
    execute(["docker", "exec", container, "nginx", "-t", "-c", "/fixture/nginx.conf",
             "-g", "pid /tmp/runtime-nginx.pid;"])
    assertApplicationErrors(port)
    time.sleep(0.1)
    accepted = assertSharedBudget(port)
    upstream = execute(["docker", "exec", container, "cat", "/tmp/upstream-access.log"])
    assert upstream.stdout.count("/rate-limit-probe/") == accepted
    assertSeparateClient(container)
    assertReadinessAndRecovery(port, int(otherBinding["HostPort"]))
    print("Nginx syntax, shared 30 r/s quota, burst 60, all methods, client isolation, forwarded-header rejection, upstream errors, readiness, and recovery passed.")


def run():
    root = Path(__file__).resolve().parents[2]
    token = secrets.token_hex(6)
    container = "backendbase-nginx-rate-limits-" + token
    try:
        with tempfile.TemporaryDirectory(prefix=container + "-") as temporary:
            runChecks(root, Path(temporary), container, token)
    finally:
        removeContainer(container, token)


if __name__ == "__main__":
    run()
