#!/usr/bin/env bash

deployLog() {
    printf '[deployment] %s\n' "$*"
}

deployFail() {
    deployLog "ERROR: $*" >&2
    exit 1
}

deployRequireCommand() {
    command -v "$1" > /dev/null 2>&1 || deployFail "Required command is unavailable: $1"
}

deployManifestValue() {
    php -r '
    $document = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $value = $document;
    foreach (explode(".", $argv[2]) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            exit(2);
        }
        $value = $value[$part];
    }
    if (is_bool($value)) {
        echo $value ? "true" : "false";
        exit;
    }
    if (!is_string($value) && !is_int($value)) {
        exit(3);
    }
    echo $value;
    ' "$1" "$2"
}

deploySha256() {
    php -r 'echo hash_file("sha256", $argv[1]);' "$1"
}

deployResolvePath() {
    php -r '
    $path = realpath($argv[1]);
    if ($path === false) {
        exit(1);
    }
    echo $path;
    ' "$1"
}

deployAcquireLock() {
    local lockPath="$1"

    if ! mkdir "$lockPath" 2> /dev/null; then
        deployFail "Another deployment owns the lock: $lockPath"
    fi

    DEPLOYMENT_LOCK_PATH="$lockPath"
}

deployValidatePaths() {
    local deployRoot="$1"
    local currentLink="$2"

    [[ "$deployRoot" == /* ]] || deployFail 'BACKENDBASE_DEPLOY_ROOT must be an absolute path.'
    [[ "$deployRoot" != '/' ]] || deployFail 'The filesystem root cannot be a deployment root.'
    [[ -z "${HOME:-}" || "$deployRoot" != "$HOME" ]] || deployFail 'The home directory cannot be a deployment root.'
    [[ "$currentLink" == "$deployRoot/"* ]] || deployFail 'BACKENDBASE_CURRENT_LINK must be inside the deployment root.'
}

deployRequireReleasePath() {
    local releasePath="$1"
    local releasesDirectory="$2"
    local resolvedReleasesDirectory

    resolvedReleasesDirectory="$(deployResolvePath "$releasesDirectory")" \
        || deployFail "The releases directory is unavailable: $releasesDirectory"
    [[ "$releasePath" == "$resolvedReleasesDirectory/"* ]] \
        || deployFail "Release path is outside $resolvedReleasesDirectory."
}

deployRequireManagedLink() {
    local linkPath="$1"

    if [[ -e "$linkPath" && ! -L "$linkPath" ]]; then
        deployFail "$linkPath must be absent or a symbolic link. Move the existing working tree before the first artifact deployment."
    fi
}

deployAtomicLink() {
    local targetPath="$1"
    local linkPath="$2"
    local temporaryLink="${linkPath}.new.$$"

    ln -s "$targetPath" "$temporaryLink"
    php -r '
    if (!rename($argv[1], $argv[2])) {
        exit(1);
    }
    ' "$temporaryLink" "$linkPath"
}

deployRunHook() {
    local hookPath="$1"
    local required="$2"
    shift 2

    if [[ -x "$hookPath" ]]; then
        "$hookPath" "$@"
        return
    fi

    if [[ "$required" == 'true' ]]; then
        deployFail "Required deployment hook is unavailable: $hookPath"
    fi

    deployLog "Optional hook is not configured: $hookPath"
}

deployCheckReadiness() {
    local readinessUrl="$1"
    local attempts="${BACKENDBASE_READINESS_ATTEMPTS:-10}"
    local timeoutSeconds="${BACKENDBASE_READINESS_TIMEOUT_SECONDS:-5}"

    curl \
        --fail \
        --silent \
        --show-error \
        --max-time "$timeoutSeconds" \
        --retry "$attempts" \
        --retry-all-errors \
        --retry-delay 1 \
        "$readinessUrl" > /dev/null
}
