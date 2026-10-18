#!/bin/sh
set -eu

hooks_directory="${XDG_CONFIG_HOME:-$HOME/.config}/git/hooks"
global_name="$(git config --global --get user.name || true)"
global_email="$(git config --global --get user.email || true)"

if [ -z "$global_name" ] || [ -z "$global_email" ]; then
    printf '%s\n' 'error: Set global user.name and user.email before installing Git attribution hooks.' >&2
    exit 1
fi

mkdir -p "$hooks_directory"

cat > "$hooks_directory/enforce-git-attribution" <<'HOOK'
#!/bin/sh
set -eu

global_name="$(git config --global --get user.name || true)"
global_email="$(git config --global --get user.email || true)"

fail() {
    printf 'error: %s\n' "$1" >&2
    exit 1
}

if [ -z "$global_name" ] || [ -z "$global_email" ]; then
    fail 'Global user.name and user.email must be configured.'
fi

expected_identity="$global_name <$global_email>"

verify_identity() {
    identity_key="$1"
    actual_identity="$(git var "$identity_key" 2>/dev/null || true)"

    case "$actual_identity" in
        "$expected_identity "*) return ;;
    esac

    fail "$identity_key must match global identity $expected_identity."
}

verify_message() {
    message_path="$1"

    if [ ! -r "$message_path" ]; then
        fail "Cannot read commit message at $message_path."
    fi

    trailer_lines="$(git interpret-trailers --parse "$message_path")" || fail 'Cannot parse commit trailers.'

    if printf '%s\n' "$trailer_lines" | grep -Eiq '^[[:space:]]*[[:alnum:]][[:alnum:]-]*-by[[:space:]]*:'; then
        fail 'Attribution trailers ending in -by are not permitted.'
    fi
}

case "$1" in
    pre-commit)
        verify_identity GIT_AUTHOR_IDENT
        verify_identity GIT_COMMITTER_IDENT
        ;;
    commit-msg)
        verify_identity GIT_AUTHOR_IDENT
        verify_identity GIT_COMMITTER_IDENT
        verify_message "$2"
        ;;
    pre-merge-commit)
        verify_identity GIT_AUTHOR_IDENT
        verify_identity GIT_COMMITTER_IDENT
        ;;
    *)
        fail "Unsupported hook entry point: $1."
        ;;
esac
HOOK

for hook_name in pre-commit commit-msg pre-merge-commit; do
    cat > "$hooks_directory/$hook_name" <<HOOK
#!/bin/sh
exec "\$(dirname "\$0")/enforce-git-attribution" $hook_name "\$@"
HOOK
done

chmod 755 "$hooks_directory/enforce-git-attribution" "$hooks_directory/pre-commit" "$hooks_directory/commit-msg" "$hooks_directory/pre-merge-commit"
git config --global core.hooksPath "$hooks_directory"

printf 'Installed global Git attribution hooks at %s.\n' "$hooks_directory"
