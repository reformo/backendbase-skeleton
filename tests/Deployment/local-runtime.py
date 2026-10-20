#!/usr/bin/env python3
"""Run only against new, owned local containers and remove them on success or failure."""

import json
from pathlib import Path
import shutil
import signal
import sys
import tempfile

sys.path.insert(0, str(Path(__file__).parent / "Runtime"))
from behavior_checks import identity_checks, worker_checks, workload_checks, interrupted_replay_checks, cold_start_checks
from environment import settings, start_services, write_settings
from release_artifact import build_release
from recovery_checks import backup_restore_checks, redis_recovery_checks, observation_checks
from runtime_checks import installation_checks
from runtime_services import RuntimeServices


def run():
    root = Path(__file__).resolve().parents[2]
    directory = Path(sys.argv[1]) if len(sys.argv) == 2 else Path(tempfile.mkdtemp(prefix="backendbase-deployment-"))
    directory = directory.resolve()
    if directory.parent != Path(tempfile.gettempdir()).resolve() or not directory.name.startswith("backendbase-deployment-") or not directory.is_dir():
        raise RuntimeError("A temporary deployment directory is required.")
    services = RuntimeServices()
    report = {"completed": False}
    try:
        archive = Path((directory / "archive-path").read_text()) if (directory / "archive-path").exists() else build_release(root, directory)
        for filename in ["deploy-release.sh", "lib.sh"]:
            shutil.copy2(root / "bin/deployment" / filename, directory / filename)
        shutil.copy2(shutil.which("composer"), directory / "composer.phar")
        shutil.copy2(root / "tests/Deployment/Runtime/queue-greeting.php", directory / "queue-greeting.php")
        values = settings()
        (directory / "password").write_text(values["FIXTURE_MYSQL_PASSWORD"])
        (directory / "password").chmod(0o600)
        write_settings(directory / "runtime.env", values)
        start_services(services, root, directory, values)
        base = "http://127.0.0.1:" + str(services.port("web", 8080))
        print("Isolated local services are ready.", flush=True)
        report.update(installation_checks(services, archive, base))
        print("Real installation and failure checks passed.", flush=True)
        token, _admin, results = identity_checks(services, base, values)
        report.update(results)
        report.update(worker_checks(services))
        print("Production HTTP, SMTP, queue, and worker restart checks passed.", flush=True)
        report.update(cold_start_checks(services, base, values, token))
        print("Cold-start requests checked; any response failures are retained in the result.", flush=True)
        report.update(workload_checks(services, base, values, token))
        print("The bounded concurrent workload passed.", flush=True)
        report.update(interrupted_replay_checks(services, base, values, token))
        print("Interrupted replay remained blocked without duplicate effects.", flush=True)
        report.update(backup_restore_checks(services, base, values))
        print("Populated backup restore and encrypted replay passed.", flush=True)
        report.update(redis_recovery_checks(services, base, values))
        report.update(observation_checks(services, values))
        print("Redis promotion, controlled logout, and diagnostic checks passed.", flush=True)
        report["allChecksPassed"] = report["coldStartConcurrentRequests"]["failures"] == 0 and report["redisOutageHasOneValidJsonProblem"]
        report["completed"] = True
    finally:
        try:
            services.cleanup()
            report["ownedServicesRemoved"] = True
        finally:
            (directory / "runtime.env").unlink(missing_ok=True)
            (directory / "password").unlink(missing_ok=True)
        destination = root / "artifacts/deployment-rehearsal"
        destination.mkdir(parents=True, exist_ok=True)
        (destination / "results.json").write_text(json.dumps(report, indent=2) + "\n")
        if report["completed"] or len(sys.argv) == 1:
            shutil.rmtree(directory)
        print("Owned containers, networks, volume, and runtime image were removed.", flush=True)

    if not report.get("allChecksPassed", False):
        raise SystemExit(1)


def interrupted(signum, frame):
    raise KeyboardInterrupt("The local rehearsal was interrupted.")


if __name__ == "__main__":
    signal.signal(signal.SIGTERM, interrupted)
    run()
