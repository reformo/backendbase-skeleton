"""Execute maintained Bruno requests and retain sanitized operation evidence."""

import json
from pathlib import Path
import re
import subprocess

ROOT = Path(__file__).resolve().parents[2]


def load_yaml(path):
    code = 'require "vendor/autoload.php"; echo json_encode(Symfony\\Component\\Yaml\\Yaml::parseFile($argv[1]));'
    return json.loads(subprocess.check_output(["php", "-r", code, str(path)], cwd=ROOT, text=True, timeout=30))


def redact(text, variables):
    for name in ["password", "apiKey", "accessToken", "verificationContextToken", "code", "email", "mobile", "identifier"]:
        value = variables.get(name)
        if value:
            text = text.replace(str(value), "[redacted]")
    return re.sub(r"eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+", "[redacted]", text)


def run_phase(client, scenario, phase, *paths):
    api = scenario["api"]
    variables = scenario["variables"]
    environment = client.directory / f"{api}-bruno.json"
    environment.write_text(json.dumps({"name": "disposable", "variables": [
        {"name": name, "value": value, "enabled": True, "type": "text"} for name, value in variables.items()
    ]}))
    environment.chmod(0o600)
    directory = ROOT / "artifacts/bruno-verification" / api
    directory.mkdir(parents=True, exist_ok=True)
    report = directory / phase
    command = ["bru", "run", *paths, "--env-file", str(environment), "--bail", "--noproxy",
               "--reporter-skip-all-headers", "--reporter-skip-body",
               "--reporter-json", str(report.with_suffix(".json")),
               "--reporter-html", str(report.with_suffix(".html"))]
    result = subprocess.run(command, cwd=ROOT / f"resources/bruno/{api}-api", capture_output=True, text=True, timeout=90)
    report.with_suffix(".log").write_text(redact(result.stdout + result.stderr, variables))
    sanitize_reports(report, variables)
    assert result.returncode == 0, f"{api} Bruno {phase} failed; inspect {report.with_suffix('.log')}"
    evidence = json.loads(report.with_suffix(".json").read_text())
    results = evidence[0]["results"]
    assert results, f"{api} Bruno {phase} executed no requests"
    for request in results:
        record_request(scenario, request)
    print(f"Bruno {api}: {phase} passed ({len(results)} requests).", flush=True)


def sanitize_reports(report, variables):
    for suffix in [".json", ".html"]:
        path = report.with_suffix(suffix)
        if path.exists():
            path.write_text(redact(path.read_text(), variables))


def record_request(scenario, result):
    assert result["status"] == "pass" and not result.get("error"), result["test"]["filename"]
    tests = result["testResults"]
    assert tests and all(test["status"] == "pass" for test in tests), "A recorded passing contract test is required"
    for direction in ["request", "response"]:
        assert result[direction].get("data") is None, "Report body must be excluded"
        assert not result[direction].get("headers"), "Report headers must be excluded"
    scenario["results"].append({"file": result["test"]["filename"], "method": result["request"]["method"],
                                "status": result["response"]["status"], "tests": len(tests)})


def record_coverage(scenarios):
    operations = []
    for scenario in scenarios:
        operations.extend(collection_coverage(scenario))
    evidence = {"cliVersion": "4.0.0", "operations": operations,
                "requests": sum(len(scenario["results"]) for scenario in scenarios),
                "passedTests": sum(result["tests"] for scenario in scenarios for result in scenario["results"])}
    (ROOT / "artifacts/bruno-verification/coverage.json").write_text(json.dumps(evidence, indent=2) + "\n")
    print(f"Bruno coverage passed: {len(operations)} operations, {evidence['requests']} requests.", flush=True)


def collection_coverage(scenario):
    collection = ROOT / f"resources/bruno/{scenario['api']}-api"
    operations = []
    for path in sorted(collection.glob("*/*.yml")):
        request = load_yaml(path)
        if "http" not in request:
            continue
        results = [result for result in scenario["results"] if result["file"] == str(path.relative_to(collection))]
        assert results, f"Unexecuted Bruno request: {path}"
        operations.append({"api": scenario["api"], "file": str(path.relative_to(collection)),
                           "method": request["http"]["method"], "path": request["http"]["url"],
                           "statuses": sorted({result["status"] for result in results})})
    return operations
