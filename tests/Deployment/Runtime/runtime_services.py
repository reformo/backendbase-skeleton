"""Create and control only resources owned by one local deployment rehearsal."""

import json
import secrets
import subprocess

LABEL = "backendbase.deployment-rehearsal"
IMAGES = {
    "mysql": "mysql@sha256:b3b90af2a6552ae30c266fdb7d5dd55f3afb72404bb78d37fe8a23eb857fd3fb",
    "redis": "redis@sha256:2c2dff791878316e3b083188be0d578423b24813cdda5de0e80ea4f7b3e6bfd6",
    "rabbitmq": "rabbitmq@sha256:06fb591136a49e861e01aaaf9ce45085839ca23c35913d45a1e83519bb9778ca",
    "nginx": "nginx@sha256:0d3b80406a13a767339fbe2f41406d6c7da727ab89cf8fae399e81f780f814d1",
    "mail": "mailhog/mailhog:latest",
    "object-store": "quay.io/minio/minio:latest",
}


def execute(arguments, *, timeout=60, input_text=None, check=True, cwd=None, environment=None):
    result = subprocess.run(arguments, input=input_text, text=True, errors="replace", capture_output=True,
                            timeout=timeout, cwd=cwd, env=environment)
    if check and result.returncode:
        raise RuntimeError(f"{arguments[0]} failed ({result.returncode}): {result.stdout[-1600:]} {result.stderr[-1600:]}")
    return result


class RuntimeServices:
    def __init__(self):
        self.token = secrets.token_hex(6)
        self.resources = {"prefix": "backendbase-deployment-" + self.token, "containers": {},
                          "network": None, "edge": None, "volume": None, "images": {}}

    def prepare(self, root):
        for name, reference in IMAGES.items():
            self.resources["images"][name] = execute(["docker", "image", "inspect", reference,
                                         "--format", "{{.Id}}"])
            self.resources["images"][name] = self.resources["images"][name].stdout.strip()
        build = execute(["docker", "build", "--quiet", "--pull=false", "--label", LABEL + "=" + self.token,
                         "--tag", self.resources["prefix"], str(root / "resources/testing/deployment-runtime")], timeout=600)
        self.resources["images"]["php"] = build.stdout.strip().splitlines()[-1]
        self.resources["network"] = execute(["docker", "network", "create", "--internal", "--label",
                                LABEL + "=" + self.token, self.resources["prefix"]]).stdout.strip()
        self.resources["edge"] = execute(["docker", "network", "create", "--label",
                              LABEL + "=" + self.token, self.resources["prefix"] + "-edge"]).stdout.strip()
        self.resources["volume"] = execute(["docker", "volume", "create", "--label", LABEL + "=" + self.token,
                               self.resources["prefix"]]).stdout.strip()

    def start(self, name, image, arguments=(), *, mounts=(), environment=None, publish=None,
              memory="256m", tmpfs=()):
        command = ["docker", "run", "--detach", "--pull", "never", "--name", self.resources["prefix"] + "-" + name,
                   "--label", LABEL + "=" + self.token, "--network", self.resources["network"],
                   "--network-alias", name, "--memory", memory]
        for mount in mounts:
            command += ["--mount", mount]
        for directory in tmpfs:
            command += ["--tmpfs", directory]
        for key, value in (environment or {}).items():
            command += ["--env", key + "=" + value]
        if publish:
            command += ["--network", self.resources["edge"], "--publish", "127.0.0.1::" + str(publish)]
        command += [self.resources["images"][image], *arguments]
        identifier = execute(command).stdout.strip()
        self.resources["containers"][name] = identifier
        return identifier

    def identify(self, name):
        identifier = self.resources["containers"][name]
        details = json.loads(execute(["docker", "inspect", identifier]).stdout)[0]
        if details["Config"]["Labels"].get(LABEL) != self.token:
            raise RuntimeError("Refusing a container outside this rehearsal.")
        if self.resources["network"] not in [item["NetworkID"] for item in details["NetworkSettings"]["Networks"].values()]:
            raise RuntimeError("The rehearsal network does not match.")
        return details

    def run(self, name, arguments, **options):
        details = self.identify(name)
        return execute(["docker", "exec", "--interactive", details["Id"], *arguments], **options)

    def port(self, name, port):
        details = self.identify(name)
        bindings = details["NetworkSettings"]["Ports"][str(port) + "/tcp"]
        if not bindings:
            logs = execute(["docker", "logs", details["Id"]], check=False)
            raise RuntimeError("Missing port binding: " + logs.stderr[-1500:] + logs.stdout[-1500:])
        binding = bindings[0]
        if binding["HostIp"] != "127.0.0.1":
            raise RuntimeError("Only a loopback publication is permitted.")
        return int(binding["HostPort"])

    def stop(self, name):
        details = self.identify(name)
        execute(["docker", "stop", "--time", "3", details["Id"]])

    def restart(self, name):
        details = self.identify(name)
        execute(["docker", "restart", "--time", "3", details["Id"]])

    def cleanup(self):
        identifiers = execute(["docker", "container", "ls", "--all", "--quiet", "--filter",
                               "label=" + LABEL + "=" + self.token]).stdout.split()
        for identifier in identifiers:
            details = json.loads(execute(["docker", "inspect", identifier]).stdout)[0]
            if details["Config"]["Labels"].get(LABEL) != self.token:
                raise RuntimeError("Refusing an unowned container during cleanup.")
            execute(["docker", "rm", "--force", identifier])
        for resource, identifier in [("volume", self.resources["volume"]), ("network", self.resources["network"]),
                                     ("network", self.resources["edge"])]:
            if not identifier:
                continue
            details = json.loads(execute(["docker", resource, "inspect", identifier]).stdout)[0]
            if details["Labels"].get(LABEL) != self.token:
                raise RuntimeError("Refusing a resource outside this rehearsal.")
            execute(["docker", resource, "rm", identifier])
        images = execute(["docker", "image", "ls", "--quiet", "--filter", "label=" + LABEL + "=" + self.token]).stdout.split()
        for identifier in images:
            details = json.loads(execute(["docker", "image", "inspect", identifier]).stdout)[0]
            if details["Config"]["Labels"].get(LABEL) != self.token:
                raise RuntimeError("Refusing an unowned runtime image.")
            execute(["docker", "image", "rm", identifier])
        for resource in ["container", "network", "volume", "image"]:
            command = ["docker", resource, "ls", "--quiet", "--filter", "label=" + LABEL + "=" + self.token]
            if resource == "container":
                command.append("--all")
            if execute(command).stdout.strip():
                raise RuntimeError("A rehearsal resource remains after cleanup.")
