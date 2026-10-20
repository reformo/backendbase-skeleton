"""Build the current workspace in an isolated source snapshot without live secrets."""

import os
from pathlib import Path
import shutil

from runtime_services import execute


def build_release(root, directory):
    snapshot = directory / "source"
    snapshot.mkdir()
    entries = execute(["git", "ls-files", "--cached", "--others", "--exclude-standard", "-z"], cwd=root).stdout
    for relative in sorted(set(entries.split("\0")) - {""}):
        path = Path(relative)
        if path.name.startswith(".env") and path.name != ".env.example":
            continue
        source = root / path
        if not source.exists():
            continue
        target = snapshot / path
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(source, target, follow_symlinks=False)
    execute(["git", "init", "--quiet", str(snapshot)])
    execute(["git", "add", "."], cwd=snapshot)
    execute(["git", "-c", "commit.gpgsign=false", "commit", "--quiet", "-m", "Local deployment fixture"], cwd=snapshot)
    identifier = execute(["git", "rev-parse", "HEAD"], cwd=snapshot).stdout.strip()
    artifacts = directory / "release"
    environment = dict(os.environ, COMPOSER_ALLOW_SUPERUSER="1")
    result = execute(["bash", "bin/deployment/build-release.sh", identifier, str(artifacts)],
                     cwd=snapshot, environment=environment, timeout=1200, check=False)
    (directory / "build.log").write_text(result.stdout + result.stderr)
    if result.returncode:
        raise RuntimeError("The isolated release build failed; inspect its private build log.")
    shutil.rmtree(snapshot)
    return artifacts / ("backendbase-" + identifier + ".tar.gz")
