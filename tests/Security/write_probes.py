"""Bounded security probes for each exercised write operation."""

import json
import secrets


def request_headers(api, method, token, overrides):
    headers = {"X-Source-Id": api, "Content-Type": "application/json"}
    if api == "example":
        headers["Backendbase-Api-Key"] = "http-fixture-api-key"
    if method in {"POST", "PUT", "PATCH", "DELETE"}:
        headers["Idempotency-Key"] = secrets.token_hex(16)
    if token:
        headers["Authorization"] = "Bearer " + token
    headers.update(overrides or {})
    return {name: value for name, value in headers.items() if value is not None}


def should_probe(method, expected, probe):
    return probe is None and method in {"POST", "PUT", "PATCH", "DELETE"} and (expected < 300 or expected == 429)


def record_operation(operation, results):
    assert results, "Uncovered operation: " + operation["operationId"]
    assert any(result["probe"] is None for result in results), "Missing lifecycle request: " + operation["operationId"]
    operation["statuses"] = sorted({result["status"] for result in results})
    operation["probes"] = sorted({result["probe"] for result in results if result["probe"]})
    if operation["method"] in {"post", "put", "patch", "delete"}:
        assert "missing-key" in operation["probes"] and "unchanged-replay" in operation["probes"], operation["operationId"]


def before_write(client, api, method, path, payload, token):
    def probe(expected, name, **options):
        return client.request(api, method, path, expected, payload, token,
                              probe=name, **options)

    probe(400, "missing-key", headers={"Idempotency-Key": None})
    probe(400, "invalid-key", headers={"Idempotency-Key": "invalid key"})
    if api == "example":
        probe(401, "missing-api-key", headers={"Backendbase-Api-Key": None})
    if token:
        probe(401, "missing-bearer", headers={"Authorization": None})
        probe(401, "invalid-bearer", headers={"Authorization": "Bearer invalid"})
    if method != "DELETE":
        probe(400, "malformed-json", raw=b'{"invalid":')
        probe(400, "oversized-body", raw=b'{"value":"' + b"x" * 65536 + b'"}')


def after_write(client, api, method, path, expected, payload, token, key, result):
    revoked = token and method == "DELETE" and path.startswith("/identity/")
    replay = client.request(api, method, path, 401 if revoked else expected, payload, token,
                            headers={"Idempotency-Key": key}, probe="unchanged-replay")
    if not revoked:
        assert replay == result, f"{api} {method} {path}: replay changed the response"
        client.request(api, method, path, 422, payload, token,
                       headers={"Idempotency-Key": key},
                       raw=(json.dumps(payload).encode() if payload is not None else b"") + b" ",
                       probe="changed-request")
    if token:
        client.request(api, method, path, 401, payload, token,
                       headers={"Idempotency-Key": key, "Authorization": None},
                       probe="authentication-before-replay")


def cross_account_contact(client, api, identifier, context, code, other_token):
    for action, payload in [("confirm", {"verificationContextToken": context, "code": code}),
                             ("resend", {"verificationContextToken": context})]:
        client.request(api, "POST", f"/identity/contact-verifications/{identifier}/{action}",
                       401, payload, other_token, probe="cross-account")


def changed_public_login(client, api, token):
    payload = {"identifier": client.operatorEmail, "password": "http-fixture-password"}
    key = secrets.token_hex(16)
    session = client.request(api, "POST", "/auth/login", 200, payload,
                             headers={"Idempotency-Key": key}, probe="public-replay-fixture")
    client.request(api, "POST", "/auth/login", 422, {**payload, "identifier": "different@example.test"},
                   headers={"Idempotency-Key": key}, probe="cross-account-public-replay")
    assert session["session"]["accessToken"] != token


def exercise_refresh(client, api, identifier):
    session = client.request(api, "POST", "/auth/login", 200,
                             {"identifier": identifier, "password": "http-fixture-password"})["session"]
    client.request(api, "POST", "/auth/refresh", 400, {"refreshToken": []})
    renewed = client.request(api, "POST", "/auth/refresh", 200,
                             {"refreshToken": session["refreshToken"]})["session"]
    assert renewed["refreshToken"] != session["refreshToken"]
    assert renewed["sessionExpiresAt"] == session["sessionExpiresAt"]
    client.request(api, "GET", "/identity", 200, token=renewed["accessToken"])
    client.request(api, "POST", "/auth/refresh", 401,
                   {"refreshToken": session["refreshToken"]}, probe="refresh-reuse")
    client.request(api, "GET", "/identity", 401, token=renewed["accessToken"])
