"""Exercise production identity HTTP routes against explicitly prepared test services."""

import base64
import hashlib
import hmac
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import socket
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
PASSWORD = "http-fixture-password"


def php(action, environment, directory, *arguments):
    output = subprocess.check_output(
        ["php", str(ROOT / "tests/Security/http-fixture.php"), action, *arguments],
        cwd=directory, env=environment, text=True, timeout=60,
    )
    return json.loads(output)


def prepare_application(directory, environment):
    for name in ["config", "public"]:
        shutil.copytree(ROOT / name, directory / name)
    for name in ["src", "vendor", "resources"]:
        (directory / name).symlink_to(ROOT / name, target_is_directory=True)
    (directory / "bin").mkdir()
    shutil.copy2(ROOT / "bin/doctrine", directory / "bin/doctrine")
    shutil.copy2(ROOT / "migrations.json", directory / "migrations.json")
    (directory / "sink").mkdir(mode=0o700)
    (directory / "var/cache").mkdir(parents=True)
    definitions = directory / "config/dependencies.php"
    definitions.rename(directory / "config/production-dependencies.php")
    definitions.write_text("""<?php
return static function (\\DI\\ContainerBuilder $builder): void {
    $production = require 'config/production-dependencies.php';
    $production($builder);
    $builder->addDefinitions([
        \\Backendbase\\Domain\\IdentityAndAccess\\Contracts\\ContactNotifier::class =>
            new \\Tests\\Security\\Http\\FileContactNotifier(getcwd() . '/sink'),
    ]);
};
""")
    environment.update({
        "BACKENDBASE_ENV": "production", "BASE_PATH": "",
        "BACKENDBASE_REDIS_HOST": "127.0.0.1",
        "BACKENDBASE_REDIS_PORT": environment["BACKENDBASE_TEST_REDIS_PORT"],
        "BACKENDBASE_REDIS_PASSWORD": environment["BACKENDBASE_TEST_REDIS_PASSWORD"],
        "BACKENDBASE_REDIS_USERNAME": "", "BACKENDBASE_REDIS_TLS": "false",
        "BACKENDBASE_RABBITMQ_HOST": "127.0.0.1", "BACKENDBASE_RABBITMQ_PORT": "1",
        "BACKENDBASE_QUEUE_DRIVER": "rabbitmq",
        "AWS_ACCESS_KEY_ID": "fixture", "AWS_SECRET_ACCESS_KEY": "fixture",
        "AWS_ENDPOINT": "http://127.0.0.1:1", "AWS_EC2_METADATA_DISABLED": "true",
        "OBJECT_STORE_ACCESS_KEY": "", "OBJECT_STORE_SECRET_KEY": "",
        "BACKENDBASE_IDENTITY_CONTACT_PROOF_KEY": base64.b64encode(b"e" * 32).decode(),
        "BACKENDBASE_IDEMPOTENCY_KEY": base64.b64encode(secrets.token_bytes(32)).decode(),
        "BACKENDBASE_IDENTITY_CONTACT_EMAIL_SENDER": "identity@example.test",
        "BACKENDBASE_IDENTITY_TOKEN_LIFETIME_SECONDS": "900",
    })
    for realm, key in [("USER", b"u" * 32), ("ADMIN", b"a" * 32)]:
        environment.update({
            f"BACKENDBASE_{realm}_JWT_ISSUER": f"https://{realm.lower()}.example.test",
            f"BACKENDBASE_{realm}_JWT_AUDIENCE": realm.lower(),
            f"BACKENDBASE_{realm}_JWT_KEY": base64.b64encode(key).decode(),
            f"BACKENDBASE_{realm}_JWT_KEY_ID": "http-fixture",
        })


class IdentityClient:
    def __init__(self, baseUrl, directory, suffix):
        self.baseUrl = baseUrl
        self.directory = directory
        self.results = []
        self.operatorEmail = "operator-" + suffix + "@example.test"
        self.userEmail = "user-" + suffix + "@example.test"
        self.operatorMobile = "+901" + suffix
        self.userMobile = "+902" + suffix

    def request(self, api, method, path, expected, payload=None, token=None):
        headers = {"X-Source-Id": api, "Content-Type": "application/json"}
        if method in {"POST", "PUT", "PATCH", "DELETE"}:
            headers["Idempotency-Key"] = secrets.token_hex(16)
        if token:
            headers["Authorization"] = "Bearer " + token
        body = None if payload is None else json.dumps(payload).encode()
        request = urllib.request.Request(self.baseUrl + "/" + api + "-api" + path,
                                         data=body, headers=headers, method=method)
        try:
            response = urllib.request.urlopen(request, timeout=15)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            status = response.status
            result = json.loads(response.read())
        assert status == expected, f"{api} {method} {path}: expected {expected}, got {status}"
        if status >= 400 and path != "/_status/ready":
            assert result["status"] == status
            assert "trace" not in result and "exception" not in result
        self.results.append({"api": api, "method": method.lower(), "path": path, "status": status})
        return result

    def login(self, api, identifier):
        return self.request(api, "POST", "/auth/login", 200,
                            {"identifier": identifier, "password": PASSWORD})["session"]["accessToken"]

    def contact(self, api, prefix, payload, value, token=None):
        context = secrets.token_urlsafe(32)
        payload = {**payload, "verificationContextToken": context}
        challenge = self.request(api, "POST", prefix, 200, payload, token)["contactInfo"]
        identifier = challenge["contactInfoId"]
        self.request(api, "POST", prefix + "/" + identifier + "/resend", 429,
                     {"verificationContextToken": context}, token)
        capture = self.directory / "sink" / (hashlib.sha256(value.encode()).hexdigest() + ".json")
        code = json.loads(capture.read_text())["code"]
        self.request(api, "POST", prefix + "/" + identifier + "/confirm", 200,
                     {"verificationContextToken": context, "code": code}, token)
        return identifier, context

    def register(self):
        context = secrets.token_urlsafe(32)
        values = {"email": self.userEmail, "mobile": self.userMobile}
        payload = {"contactType": "mobile", "contactValue": self.userMobile, "verificationContextToken": context}
        challenge = self.request("user", "POST", "/auth/contact-verifications", 200, payload)["contactInfo"]
        identifier = challenge["contactInfoId"]
        self.request("user", "POST", f"/auth/contact-verifications/{identifier}/resend", 429,
                     {"verificationContextToken": context})
        capture = self.directory / "sink" / (hashlib.sha256(self.userMobile.encode()).hexdigest() + ".json")
        code = json.loads(capture.read_text())["code"]
        self.request("user", "POST", f"/auth/contact-verifications/{identifier}/confirm", 400,
                     {"verificationContextToken": context, "code": "invalid"})
        self.request("user", "POST", f"/auth/contact-verifications/{identifier}/confirm", 200,
                     {"verificationContextToken": context, "code": code})
        return self.request("user", "POST", "/auth/register", 201,
                            {**values, "password": PASSWORD,
                             "verificationContextToken": context})["registration"]


def expired_token(token, key):
    header, payload, _ = token.split(".")
    claims = json.loads(base64.urlsafe_b64decode(payload + "=="))
    claims.update({"iat": int(time.time()) - 120, "nbf": int(time.time()) - 120, "exp": int(time.time()) - 60})
    payload = base64.urlsafe_b64encode(json.dumps(claims).encode()).decode().rstrip("=")
    message = header + "." + payload
    signature = base64.urlsafe_b64encode(hmac.new(key, message.encode(), hashlib.sha256).digest()).decode().rstrip("=")
    return message + "." + signature


def exercise(client):
    for api in ["user", "system-admin"]:
        client.request(api, "GET", "/_status", 200)
        ready = client.request(api, "GET", "/_status/ready", 503)
        assert ready["checks"]["mysql"] == "ready" and ready["checks"]["redis"] == "ready"
        client.request(api, "GET", "/identity", 401)
        client.request(api, "POST", "/auth/login", 400, {"identifier": []})
        client.request(api, "POST", "/auth/login", 400, {"identifier": "x" * 255, "password": PASSWORD})
    user = client.register()
    userToken = client.login("user", client.userEmail)
    adminToken = client.login("system-admin", client.operatorEmail)
    client.contact("user", "/identity/contact-verifications", {"contactType": "email"},
                   client.userEmail, userToken)
    for api, token, wrong, key in [("user", userToken, adminToken, b"u" * 32),
                                   ("system-admin", adminToken, userToken, b"a" * 32)]:
        client.request(api, "GET", "/identity", 200, token=token)
        client.request(api, "GET", "/identity", 401, token=wrong)
        client.request(api, "GET", "/identity", 401, token=expired_token(token, key))
    invitation = client.request("system-admin", "POST", "/administration/admins/invite", 201,
                                {"accountId": user["accountId"], "evidence": "http:invite"}, adminToken)["invitation"]
    client.request("system-admin", "POST", "/auth/login", 401,
                   {"identifier": client.userEmail, "password": PASSWORD})
    client.request("system-admin", "POST", "/administration/admins/approve", 200,
                   {"actorId": invitation["actorId"], "evidence": "http:approve"}, adminToken)
    for action in ["remove", "restore"]:
        client.request("system-admin", "POST", "/administration/actors/change", 200,
                       {"actorId": user["actorId"], "realm": "USER", "action": action}, adminToken)
        client.request("system-admin", "GET", "/administration/actors/USER/" + user["actorId"], 200, token=adminToken)
    client.contact("system-admin", "/auth/contact-verifications",
                   {"identifier": client.operatorEmail, "password": PASSWORD, "contactType": "email"},
                   client.operatorEmail)
    client.contact("system-admin", "/identity/contact-verifications", {"contactType": "mobile"},
                   client.operatorMobile, adminToken)
    for api, identifier in [("user", client.userEmail), ("system-admin", client.operatorEmail)]:
        for path in ["/identity/token", "/identity/session"]:
            token = client.login(api, identifier)
            client.request(api, "DELETE", path, 200, token=token)
            client.request(api, "GET", "/identity", 401, token=token)
    for api, identifier in [("user", client.userEmail), ("system-admin", client.operatorEmail)]:
        token = client.login(api, identifier)
        client.request(api, "DELETE", "/identity/account", 200, token=token)
        client.request(api, "GET", "/identity", 401, token=token)
        client.request(api, "POST", "/auth/login", 401, {"identifier": identifier, "password": PASSWORD})


def record_coverage(client):
    code = '''require "vendor/autoload.php"; $result=[];
foreach (["user", "system-admin"] as $api) {
 $spec=Symfony\\Component\\Yaml\\Yaml::parseFile("public/$api-api/docs/$api-api-merged.yml");
 foreach ($spec["paths"] as $path=>$methods) { foreach ($methods as $method=>$operation) {
  if (isset($operation["operationId"])) $result[]=["api"=>$api,"path"=>$path,"method"=>$method,"operationId"=>$operation["operationId"]];
 } } } echo json_encode($result);'''
    inventory = json.loads(subprocess.check_output(["php", "-r", code], cwd=ROOT, text=True))
    for operation in inventory:
        pattern = re.sub(r"\{[^}]+\}", "[^/]+", operation["path"])
        operation["statuses"] = sorted({result["status"] for result in client.results
                                         if result["api"] == operation["api"]
                                         and result["method"] == operation["method"]
                                         and re.fullmatch(pattern, result["path"])})
        assert operation["statuses"], "Uncovered operation: " + operation["operationId"]
    evidence = ROOT / "artifacts/identity-http.json"
    evidence.parent.mkdir(exist_ok=True)
    evidence.write_text(json.dumps({"operations": inventory, "requests": len(client.results)}, indent=2) + "\n")
    print(f"Identity HTTP checks passed: {len(inventory)} operations, {len(client.results)} requests.")


def main():
    environment = dict(os.environ)
    assert environment.get("BACKENDBASE_SERVICE_TESTS") == "1", "Prepared disposable services are required."
    database = php("create", environment, ROOT)
    try:
        with tempfile.TemporaryDirectory(prefix="backendbase-identity-http-") as temporary:
            directory = Path(temporary)
            prepare_application(directory, environment)
            password = urllib.parse.quote(environment["BACKENDBASE_TEST_MYSQL_PASSWORD"], safe="")
            port = environment["BACKENDBASE_TEST_MYSQL_PORT"]
            environment["BACKENDBASE_DB_DSN"] = f"mysql://root:{password}@127.0.0.1:{port}/{database}?charset=utf8mb4"
            subprocess.run(["php", "bin/doctrine", "migrations:migrate", "--no-interaction"],
                           cwd=directory, env=environment, check=True, timeout=60, stdout=subprocess.DEVNULL)
            suffix = str(secrets.randbelow(900000000) + 100000000)
            environment["BACKENDBASE_HTTP_FIXTURE_SUFFIX"] = suffix
            php("seed", environment, directory)
            with socket.socket() as reservation:
                reservation.bind(("127.0.0.1", 0))
                port = reservation.getsockname()[1]
            with (directory / "server.log").open("w+") as log:
                server = subprocess.Popen(["php", "-d", "expose_php=0", "-S", f"127.0.0.1:{port}", "-t", "public"],
                                          cwd=directory, env=environment, stdout=log, stderr=log)
                try:
                    for attempt in range(50):
                        try:
                            with socket.create_connection(("127.0.0.1", port), timeout=1):
                                break
                        except OSError:
                            time.sleep(0.1)
                    client = IdentityClient(f"http://127.0.0.1:{port}", directory, suffix)
                    exercise(client)
                    record_coverage(client)
                    if "--scan" in sys.argv:
                        from identity_zap import scan
                        environment["BACKENDBASE_HTTP_FIXTURE_SUFFIX"] = str(secrets.randbelow(900000000) + 100000000)
                        php("seed", environment, directory)
                        scanClient = IdentityClient(client.baseUrl, directory, environment["BACKENDBASE_HTTP_FIXTURE_SUFFIX"])
                        scan(scanClient, ROOT)
                except Exception:
                    log.flush()
                    evidence = ROOT / "artifacts/identity-http-server.log"
                    evidence.parent.mkdir(exist_ok=True)
                    shutil.copy2(directory / "server.log", evidence)
                    raise
                finally:
                    server.terminate()
                    server.wait(timeout=10)
    finally:
        php("destroy", environment, ROOT, database)


if __name__ == "__main__":
    main()
