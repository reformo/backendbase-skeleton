"""Run bounded authenticated ZAP scans after the identity lifecycle checks."""

import json
from pathlib import Path
import shutil
import secrets
import subprocess
import sys
import urllib.parse

IMAGE = "ghcr.io/zaproxy/zaproxy@sha256:781a2bdaea47324e7bab583e2263f21d257b0aee61ed51521a5be45f5f5081ef"


def scan(client, root):
    directory = client.directory / "zap"
    directory.mkdir(mode=0o777)
    directory.chmod(0o777)
    shutil.copy2(root / ".zap/rules.tsv", directory / "rules.tsv")
    port = urllib.parse.urlparse(client.base_url).port
    host = "host.docker.internal" if sys.platform == "darwin" else "127.0.0.1"
    target = f"http://{host}:{port}"
    evidence = root / "artifacts/zap-identity"
    evidence.mkdir(parents=True, exist_ok=True)
    for api in ["user", "system-admin"]:
        token = client.login(api, client.operator_email)
        client.request(api, "GET", "/identity", 200, token=token)
        specification = subprocess.check_output([
            "php", "-r",
            'require "vendor/autoload.php"; echo json_encode(Symfony\\Component\\Yaml\\Yaml::parseFile($argv[1]));',
            str(root / f"public/{api}-api/docs/{api}-api-merged.yml"),
        ], cwd=root, text=True, timeout=30)
        specification = json.loads(specification)
        specification["paths"] = {path: specification["paths"][path] for path in ["/identity", "/_status"]}
        specification["servers"] = [{"url": target + "/" + api + "-api"}]
        (directory / f"{api}.json").write_text(json.dumps(specification))
        arguments = []
        for index, (header, value) in enumerate([("X-Source-Id", api), ("Authorization", "Bearer " + token)]):
            for name, setting in [("description", header), ("enabled", "true"), ("matchtype", "REQ_HEADER"),
                                  ("matchstr", header), ("regex", "false"), ("replacement", value)]:
                arguments.append(f'-config replacer.full_list({index}).{name}="{setting}"')
        container = "backendbase-identity-zap-" + secrets.token_hex(8)
        command = ["docker", "run", "--rm", "--name", container, "--network", "host", "-v", f"{directory}:/zap/wrk:rw",
                   IMAGE, "zap-api-scan.py", "-t", f"/zap/wrk/{api}.json", "-f", "openapi",
                   "-c", "/zap/wrk/rules.tsv", "-T", "3", "-r", f"{api}.html",
                   "-J", f"{api}-report.json", "-z", " ".join(arguments)]
        try:
            result = subprocess.run(command, capture_output=True, text=True, timeout=360)
        finally:
            subprocess.run(["docker", "rm", "--force", container], capture_output=True, timeout=30)
        (evidence / f"{api}.log").write_text((result.stdout + result.stderr).replace(token, "[redacted]"))
        for name in [f"{api}.html", f"{api}-report.json"]:
            report = directory / name
            if report.exists():
                (evidence / name).write_text(report.read_text().replace(token, "[redacted]"))
        assert result.returncode == 0, f"{api} ZAP failed with status {result.returncode}; inspect {evidence / (api + '.log')}"
        client.request(api, "GET", "/identity", 200, token=token)
        print(f"Authenticated ZAP scan passed: {api}", flush=True)
    (evidence / "scope.json").write_text(json.dumps({
        "image": IMAGE, "scannedPaths": ["GET /identity", "GET /_status"],
        "excludedPaths": "Mutations and expected-unavailable readiness use the complete deterministic HTTP lifecycle checks.",
    }, indent=2) + "\n")
