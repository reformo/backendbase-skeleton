"""Settings for disposable services. Never read the checkout's environment file."""

import base64
import secrets
import shlex
import time


def wait_until(probe, description, timeout=90):
    deadline = time.monotonic() + timeout
    while time.monotonic() < deadline:
        if probe():
            return
        time.sleep(0.5)
    raise RuntimeError("Timed out: " + description)


def settings():
    password = secrets.token_hex(24)
    values = {
        "BACKENDBASE_DEPLOYMENT_REHEARSAL": "1", "BACKENDBASE_ENV": "production", "BACKENDBASE_SERVICE_NAME": "deployment-rehearsal",
        "BACKENDBASE_DB_DSN": f"mysql://root:{password}@mysql:3306/deployment?charset=utf8mb4",
        "FIXTURE_MYSQL_PASSWORD": password, "EXAMPLE_API_KEY": secrets.token_hex(24),
        "BACKENDBASE_LOGGER_HANDLER": "stream", "BACKENDBASE_LOGGER_LEVEL": "warning",
        "BACKENDBASE_QUEUE_DRIVER": "rabbitmq", "BACKENDBASE_RABBITMQ_HOST": "rabbitmq",
        "BACKENDBASE_RABBITMQ_USER": "rehearsal", "BACKENDBASE_RABBITMQ_PASSWORD": password,
        "BACKENDBASE_RABBITMQ_VHOST": "/", "BACKENDBASE_RABBITMQ_QUEUE_NAME": "deployment-queue",
        "BACKENDBASE_RABBITMQ_EXCHANGE_NAME": "deployment", "BACKENDBASE_RABBITMQ_PORT": "5672",
        "BACKENDBASE_RABBITMQ_EXCHANGE_TYPE": "direct", "BACKENDBASE_REDIS_HOST": "redis",
        "BACKENDBASE_REDIS_PASSWORD": password, "BACKENDBASE_REDIS_PORT": "6379",
        "BACKENDBASE_REDIS_TLS": "false", "BACKENDBASE_USER_ACCESS_TOKEN_LIFETIME_SECONDS": "900",
        "BACKENDBASE_USER_SESSION_IDLE_TIMEOUT_SECONDS": "86400",
        "BACKENDBASE_USER_SESSION_MAXIMUM_LIFETIME_SECONDS": "604800",
        "BACKENDBASE_ADMIN_ACCESS_TOKEN_LIFETIME_SECONDS": "300",
        "BACKENDBASE_ADMIN_SESSION_IDLE_TIMEOUT_SECONDS": "1800",
        "BACKENDBASE_ADMIN_SESSION_MAXIMUM_LIFETIME_SECONDS": "28800",
        "BACKENDBASE_IDENTITY_REGISTRATION_REQUIRE_MOBILE_VERIFICATION": "false",
        "BACKENDBASE_IDENTITY_REGISTRATION_REQUIRE_EMAIL_VERIFICATION": "false",
        "BACKENDBASE_IDENTITY_CONTACT_EMAIL_SENDER": "rehearsal@example.test",
        "BACKENDBASE_EMAIL_DRIVER": "smtp", "SMTP_HOST": "mail", "SMTP_PORT": "1025",
        "SMTP_ENCRYPTION": "none", "SMTP_TIMEOUT_SECONDS": "3", "BACKENDBASE_SMS_DRIVER": "sns",
        "AWS_ACCESS_KEY_ID": "local-rehearsal", "AWS_SECRET_ACCESS_KEY": password,
        "AWS_REGION": "eu-central-1", "AWS_ENDPOINT": "http://mail:4566",
        "OBJECT_STORE_ACCESS_KEY": "rehearsal", "OBJECT_STORE_SECRET_KEY": password,
        "OBJECT_STORE_REGION": "eu-central-1", "OBJECT_STORE_ENDPOINT": "http://object-store:9000",
        "OBJECT_STORE_BUCKET_NAME": "deployment-rehearsal",
        "BACKENDBASE_DEPLOY_ROOT": "/srv/backendbase",
        "BACKENDBASE_READINESS_URL": "http://web:8080/internal-ready",
        "PHPRC": "/fixture/php.ini", "PHP_INI_SCAN_DIR": "/run/php-conf",
    }
    for realm, api in [("USER", "user-api"), ("ADMIN", "system-admin-api")]:
        prefix = "BACKENDBASE_" + realm + "_JWT_"
        values.update({prefix + "ISSUER": "http://rehearsal/" + api, prefix + "AUDIENCE": api,
                       prefix + "KEY_ID": realm.lower() + "-rehearsal",
                       prefix + "KEY": base64.b64encode(secrets.token_bytes(32)).decode()})
    values["BACKENDBASE_IDENTITY_CONTACT_PROOF_KEY_ID"] = "proof-rehearsal"
    for key in ["BACKENDBASE_IDENTITY_CONTACT_PROOF_KEY", "BACKENDBASE_IDEMPOTENCY_KEY"]:
        values[key] = base64.b64encode(secrets.token_bytes(32)).decode()
    return values


def write_settings(path, values):
    path.write_text("\n".join(key + "=" + shlex.quote(value) for key, value in values.items()) + "\n")
    path.chmod(0o600)


def start_services(services, root, directory, values):
    services.prepare(root)
    password = values["FIXTURE_MYSQL_PASSWORD"]
    services.start("mysql", "mysql", environment={"MYSQL_ROOT_PASSWORD": password,
                   "MYSQL_DATABASE": "deployment"}, memory="1g", tmpfs=["/var/lib/mysql:rw,size=768m"])
    redis = ["redis-server", "--requirepass", password, "--save", "", "--appendonly", "no"]
    services.start("redis", "redis", redis)
    services.start("replica", "redis", redis + ["--replicaof", "redis", "6379", "--masterauth", password])
    services.start("rabbitmq", "rabbitmq", environment={"RABBITMQ_DEFAULT_USER": "rehearsal",
                   "RABBITMQ_DEFAULT_PASS": password}, memory="512m")
    services.start("mail", "mail", publish=8025)
    services.start("object-store", "object-store", ["server", "/data"],
                   environment={"MINIO_ROOT_USER": "rehearsal", "MINIO_ROOT_PASSWORD": password,
                                "MINIO_REGION_NAME": "eu-central-1"}, memory="512m", tmpfs=["/data:rw,size=128m"])
    fixture = root / "resources/testing/deployment-runtime"
    mounts = ["type=bind,src=" + str(fixture) + ",dst=/fixture,readonly",
              "type=bind,src=" + str(directory) + ",dst=/input,readonly",
              "type=volume,src=" + services.resources["volume"] + ",dst=/srv/backendbase"]
    services.start("application", "php", ["bash", "/fixture/entrypoint.sh"], mounts=mounts, memory="1g")
    services.start("web", "nginx", mounts=[mounts[0], mounts[2] + ",readonly",
                   "type=bind,src=" + str(root / "resources/deployment/nginx") + ",dst=/etc/nginx/backendbase,readonly",
                   "type=bind,src=" + str(fixture / "nginx.conf") + ",dst=/etc/nginx/nginx.conf,readonly"], publish=8080)
    wait_until(lambda: services.run("mysql", ["sh", "-c", 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -N -e "SELECT 1"'], check=False).returncode == 0,
               "temporary MySQL readiness")
    wait_until(lambda: services.run("rabbitmq", ["rabbitmq-diagnostics", "-q", "ping"], check=False).returncode == 0,
               "temporary RabbitMQ readiness")
    wait_until(lambda: services.run("application", ["test", "-S", "/run/rehearsal/supervisor.sock"], check=False).returncode == 0,
               "temporary process supervisor")

    wait_until(lambda: services.run("application", ["curl", "--fail", "--silent", "--max-time", "3",
               "http://object-store:9000/minio/health/ready"], check=False).returncode == 0, "local object store readiness")
    services.run("application", ["bash", "-c", 'set -ae; source /run/backendbase/runtime.env; curl --fail --silent --show-error --max-time 10 --aws-sigv4 aws:amz:eu-central-1:s3 --user "rehearsal:$FIXTURE_MYSQL_PASSWORD" -X PUT http://object-store:9000/deployment-rehearsal'])
