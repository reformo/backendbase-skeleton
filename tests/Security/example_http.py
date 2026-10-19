"""Live ExampleApi lifecycle using its own disposable User Account."""

from write_probes import changed_public_login


def exercise_example(client):
    api = "example"
    client.request(api, "GET", "/", 200)
    client.request(api, "GET", "/_status", 200)
    readiness = client.request(api, "GET", "/_status/ready", 503)
    assert readiness["checks"]["mysql"] == "ready" and readiness["checks"]["redis"] == "ready"
    client.request(api, "GET", "/identity", 401)
    email = client.userEmail.replace("user-", "example-")
    mobile = client.userMobile.replace("+902", "+903")
    client.register(api, email, mobile)
    token = client.login(api, email)
    operator = client.login(api, client.operatorEmail)
    changed_public_login(client, api, operator)
    client.request(api, "GET", "/identity", 200, token=token)
    admin = client.login("system-admin", client.operatorEmail)
    client.request(api, "GET", "/identity", 401, token=admin)
    client.contact(api, "/identity/contact-verifications", {"contactType": "email"}, email, token, operator)
    prefix = "/example-types/system/groups/security/examples"
    payload = {"lookupKey": "security-fixture", "lookupValue": "original", "details": {"source": "security"}}
    client.request(api, "POST", prefix, 204, payload, token)
    client.request(api, "GET", "/example-types/system/groups", 200, token=token)
    collection = client.request(api, "GET", prefix, 200, token=token)
    assert collection["total"] == 1
    path = prefix + "/security-fixture"
    client.request(api, "GET", path, 200)
    client.request(api, "PATCH", path, 204, {"lookupValue": "changed"}, token)
    assert client.request(api, "GET", path, 200)["example"]["lookupValue"] == "changed"
    client.request(api, "POST", prefix, 400, {**payload, "lookupValue": []}, token)
    client.request(api, "PATCH", path, 400, {"details": []}, token)
    client.request(api, "DELETE", path, 204, token=token)
    client.request(api, "GET", path, 404)
    for path in ["/identity/token", "/identity/session", "/identity/account"]:
        token = client.login(api, email)
        client.request(api, "DELETE", path, 200, token=token)
        client.request(api, "GET", "/identity", 401, token=token)
