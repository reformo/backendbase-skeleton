# Security Policy

## Supported Versions

Security fixes apply to the current `trunk` branch and the latest published release. Older releases can require an upgrade before a fix is available.

## Report a Vulnerability

Do not report a vulnerability through a public issue, discussion, or pull request.

Send the report to `mehmet@mkorkmaz.com` with the subject `Backendbase Core security report`.

Include the following information when possible:

- The affected component and version or commit.
- The conditions needed to reproduce the issue.
- A minimal proof of concept.
- The expected and actual behavior.
- The possible effect and affected data.
- A proposed fix or mitigation, if available.

Do not include active credentials, access tokens, personal data, or production data. Use redacted examples.

## Coordinated Disclosure

Allow the maintainers time to confirm and repair the issue before public disclosure. The maintainers will coordinate release and disclosure details with the reporter.

## Security Boundaries

Dependency audits cover known published advisories only. They do not replace secret scanning, configuration review, threat modeling, or runtime security testing.

## Composer Supply-Chain Controls

Continuous integration uses Composer 2.10.3. The repository enables Composer policy checks, secure HTTP, and disables distribution-to-source fallback.

The weekly supply-chain workflow checks the pinned Composer executable and audits `composer.lock`. Pull requests that change Composer metadata run the same workflow.

The workflow installs packages with `--no-plugins --no-scripts` in a read-only job. It then verifies two committed files:

- [`resources/security/composer-sbom.cdx.json`](resources/security/composer-sbom.cdx.json) is the CycloneDX Software Bill of Materials (SBOM).
- [`resources/security/composer-package-content.json`](resources/security/composer-package-content.json) records one SHA-256 content digest for each locked package.

Regenerate both files only after you review `composer.json`, `composer.lock`, and the dependency source changes:

```sh
reviewDirectory="$(mktemp -d "${TMPDIR:-/tmp}/backendbase-composer-review.XXXXXX")"
COMPOSER_VENDOR_DIR="$reviewDirectory/vendor" composer install \
    --no-interaction --no-progress --prefer-dist --no-plugins --no-scripts
php bin/composer-supply-chain.php generate "$reviewDirectory/vendor"
php bin/composer-supply-chain.php check "$reviewDirectory/vendor"
rm -rf -- "$reviewDirectory"
```

Do not approve an unexplained digest change. A matching digest proves that package content matches the reviewed baseline. It does not prove that the package code is safe.
