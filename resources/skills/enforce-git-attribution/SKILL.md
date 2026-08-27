---
name: enforce-git-attribution
description: Configure global Git hooks that enforce the identity in the global Git configuration and reject attribution trailers. Use when a user needs consistent Git author and committer data across local repositories. Do not use for repository-only or server-side Git policies.
---

# Enforce Git Attribution

Configure this policy only after the user authorizes changes to their global Git configuration and home-directory hook files.

## Discover the Target Configuration

1. Read `git config --global --get user.name`.
2. Read `git config --global --get user.email`.
3. Read `git config --global --get core.hooksPath`.
4. Ask for the canonical name and email if either identity value is absent.

Do not infer an identity from existing commits. Do not place a name or email in the hook source. The hooks must read the global Git configuration at commit time.

## Install the Policy

Run [the installation script](scripts/install-global-hooks.sh). It sets global `core.hooksPath` and writes these hook entry points in the selected directory:

- `pre-commit` verifies the effective author and committer.
- `commit-msg` verifies the identity and blocks `*-by:` attribution trailers, including in merge commits.
- `pre-merge-commit` verifies the identity before Git creates a merge commit.

The script rejects a commit when the effective author or committer does not match the current global `user.name` and `user.email`. This catches command-line `--author` settings and `GIT_AUTHOR_*` or `GIT_COMMITTER_*` environment overrides.

The trailer rule blocks all `*-by:` trailers. This includes `Co-authored-by`, `Reviewed-by`, and `Signed-off-by`. Change that scope only when the user explicitly requests an exception.

## Verify

1. Confirm `git config --global --get core.hooksPath` returns the installed directory.
2. In a temporary repository, make one valid commit.
3. Confirm a commit with a different `--author` value fails.
4. Confirm a commit message containing `Co-authored-by:` fails.
5. Create a non-fast-forward merge and confirm `pre-merge-commit` runs.

Git client hooks do not stop a user who changes `core.hooksPath` or uses `--no-verify`. Use server-side policy when that stronger protection is required.
