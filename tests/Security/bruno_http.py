"""Run all three Bruno collections against the disposable identity HTTP fixture."""

import hashlib
import json
import secrets
import subprocess
import time

from bruno_runner import ROOT, load_yaml, record_coverage, run_phase

PASSWORD = "http-fixture-password"


def scenario(client, api, email, mobile):
    environment = load_yaml(ROOT / f"resources/bruno/{api}-api/environments/local.yml")
    variables = {item["name"]: item["value"] for item in environment["variables"]}
    variables.update({"baseUrl": f"{client.baseUrl}/{api}-api", "apiKey": "http-fixture-api-key",
                      "email": email, "mobile": mobile, "identifier": email, "password": PASSWORD,
                      "firstName": "Fixture", "lastName": "User", "contactType": "mobile", "contactValue": mobile,
                      "verificationContextToken": secrets.token_urlsafe(32), "example-key": secrets.token_hex(12)})
    return {"api": api, "variables": variables, "results": [], "authentication": "identity"}


def fixture_request(client, state, method, path, payload=None, token=None):
    return client.request(state["api"], method, path, 200, payload, token, probe="bruno-fixture")


def contact_request(client, state, authenticated):
    variables = state["variables"]
    variables["verificationContextToken"] = secrets.token_urlsafe(32)
    payload = {"verificationContextToken": variables["verificationContextToken"], "contactType": variables["contactType"]}
    prefix = "/identity/contact-verifications" if authenticated else "/auth/contact-verifications"
    folder = "identity" if authenticated else state["authentication"]
    name = "authenticated-contact" if authenticated else "contact"
    token = variables["accessToken"] if authenticated else None
    contact_payload(state, payload, authenticated)
    run_phase(client, state, name + "-request", f"{folder}/{name}-request.yml")
    state["resendAfter"] = time.monotonic() + 61
    variables["contactInfoId"] = fixture_request(client, state, "POST", prefix, payload, token)["contactInfo"]["contactInfoId"]


def contact_payload(state, payload, authenticated):
    if authenticated:
        return
    if state["api"] == "system-admin":
        payload.update({"identifier": state["variables"]["identifier"], "password": PASSWORD})
        return
    payload["contactValue"] = state["variables"]["contactValue"]


def contact_finish(client, state, authenticated):
    while time.monotonic() < state["resendAfter"]:
        time.sleep(max(0, min(1, state["resendAfter"] - time.monotonic())))
    folder = "identity" if authenticated else state["authentication"]
    name = "authenticated-contact" if authenticated else "contact"
    run_phase(client, state, name + "-resend", f"{folder}/{name}-resend.yml")
    variables = state["variables"]
    value = variables["email"] if variables["contactType"] == "email" else variables["mobile"]
    capture = client.directory / "sink" / (hashlib.sha256(value.encode()).hexdigest() + ".json")
    variables["code"] = json.loads(capture.read_text())["code"]
    run_phase(client, state, name + "-confirm", f"{folder}/{name}-confirm.yml")


def establish_session(client, state):
    run_phase(client, state, "identity", state["authentication"] + "/password-login.yml", "identity/read-current-identity.yml")
    token = fixture_request(client, state, "POST", "/auth/login",
                            {"identifier": state["variables"]["email"], "password": PASSWORD})["session"]["accessToken"]
    state["variables"]["accessToken"] = token
    principal = fixture_request(client, state, "GET", "/identity", token=token)
    key = "admin" if state["api"] == "system-admin" else "user"
    state["accountId"] = principal[key]["accountId"]
    state["variables"]["contactType"] = "email"


def administration(client, states):
    state = states[2]
    state["variables"].update({"targetAccountId": states[0]["accountId"], "targetRealm": "ADMIN",
                               "actorAction": "remove", "invitationEvidence": "bruno:invite", "approvalEvidence": "bruno:approve"})
    run_phase(client, state, "administration", "identity/invite-admin.yml", "identity/approve-admin.yml",
              "identity/remove-or-restore-actor.yml", "identity/read-actor-history.yml")


def close_sessions(client, state):
    run_phase(client, state, "revoke", "identity/revoke-current-access-token.yml")
    for phase, path in [("logout", "end-current-session"), ("closure", "close-account")]:
        run_phase(client, state, phase, state["authentication"] + "/password-login.yml", f"identity/{path}.yml")


def exercise_bruno(client):
    version = subprocess.check_output(["bru", "--version"], text=True, timeout=15).strip()
    assert version == "4.0.0", "Use the Bruno CLI version pinned by the security workflow."
    states = [scenario(client, "example", client.userEmail.replace("user-", "example-"), client.userMobile.replace("+902", "+903")),
              scenario(client, "user", client.userEmail, client.userMobile),
              scenario(client, "system-admin", client.operatorEmail, client.operatorMobile)]
    states[0]["authentication"] = "authentication"
    for state in states:
        run_phase(client, state, "system", "system")
        contact_request(client, state, False)
    for state in states:
        contact_finish(client, state, False)
    for state in states[:2]:
        run_phase(client, state, "registration", state["authentication"] + "/register-user.yml")
    for state in states:
        establish_session(client, state)
        contact_request(client, state, True)
    for state in states:
        contact_finish(client, state, True)
    run_phase(client, states[0], "examples", "example")
    administration(client, states)
    for state in states:
        close_sessions(client, state)
    record_coverage(states)
