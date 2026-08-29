<?php

declare(strict_types=1);

namespace Backendbase\ComposerSupplyChain;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

use function array_key_exists;
use function array_merge;
use function array_values;
use function count;
use function dirname;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function fwrite;
use function hash;
use function hash_file;
use function implode;
use function is_array;
use function is_dir;
use function is_file;
use function is_link;
use function is_string;
use function json_decode;
use function json_encode;
use function ksort;
use function mkdir;
use function rawurlencode;
use function readlink;
use function realpath;
use function sort;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const PHP_EOL;
use const STDERR;
use const STDOUT;

const CONTENT_MANIFEST = 'resources/security/composer-package-content.json';
const SBOM             = 'resources/security/composer-sbom.cdx.json';

try {
    $root       = dirname(__DIR__);
    $arguments  = commandArguments();
    $command    = $arguments[1] ?? '';
    $vendorPath = $arguments[2] ?? $root . '/vendor';

    if ($command !== 'generate' && $command !== 'check') {
        throw new RuntimeException('Usage: composer-supply-chain.php <generate|check> [vendor-directory]');
    }

    $lock       = readJsonObject($root . '/composer.lock');
    $rootConfig = readJsonObject($root . '/composer.json');
    $sbom       = softwareBillOfMaterials($lock, $rootConfig);
    $manifest   = packageContentManifest($lock, $vendorPath, $root . '/composer.lock');

    if ($command === 'generate') {
        writeEvidence($root, SBOM, $sbom);
        writeEvidence($root, CONTENT_MANIFEST, $manifest);
        fwrite(STDOUT, sprintf('Generated Composer SBOM and content digests for %d packages.', count($manifest['packages'])) . PHP_EOL);
        exit(0);
    }

    verifyEvidence($root, SBOM, $sbom);
    verifyEvidence($root, CONTENT_MANIFEST, $manifest);
    fwrite(STDOUT, sprintf('Verified Composer SBOM and content digests for %d packages.', count($manifest['packages'])) . PHP_EOL);
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

/** @return list<string> */
function commandArguments(): array
{
    $arguments = $_SERVER['argv'] ?? [];
    if (! is_array($arguments)) {
        throw new RuntimeException('Command arguments are invalid.');
    }

    $validated = [];
    foreach ($arguments as $argument) {
        if (! is_string($argument)) {
            throw new RuntimeException('Command arguments must be strings.');
        }

        $validated[] = $argument;
    }

    return $validated;
}

/** @return array<string, mixed> */
function readJsonObject(string $path): array
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException(sprintf('Cannot read JSON file: %s', $path));
    }

    $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($decoded)) {
        throw new RuntimeException(sprintf('JSON root must be an object: %s', $path));
    }

    return $decoded;
}

/**
 * @param array<string, mixed> $lock
 * @param array<string, mixed> $rootConfig
 *
 * @return array<string, mixed>
 */
function softwareBillOfMaterials(array $lock, array $rootConfig): array
{
    $rootName      = requiredString($rootConfig, 'name', 'composer.json');
    $rootReference = packageReference($rootConfig);
    $packages      = lockedPackages($lock);
    $components    = [];
    $dependencies  = [];
    $references    = [];

    foreach ($packages as $package) {
        $name              = requiredString($package, 'name', 'locked package');
        $reference         = packageBomReference($package);
        $references[$name] = $reference;
        $components[]      = sbomComponent($package);
    }

    foreach ($packages as $package) {
        $dependencies[] = sbomDependency($package, $references);
    }

    $dependencies[] = [
        'ref' => packageUrl($rootName, $rootReference),
        'dependsOn' => directDependencyReferences($rootConfig, $references),
    ];

    sortByReference($components, 'bom-ref');
    sortByReference($dependencies, 'ref');

    return [
        'bomFormat' => 'CycloneDX',
        'specVersion' => '1.6',
        'version' => 1,
        'metadata' => [
            'component' => [
                'type' => 'application',
                'bom-ref' => packageUrl($rootName, $rootReference),
                'name' => $rootName,
                'version' => $rootReference,
                'purl' => packageUrl($rootName, $rootReference),
            ],
            'properties' => [
                ['name' => 'backendbase:composer-lock-content-hash', 'value' => optionalString($lock, 'content-hash')],
            ],
        ],
        'components' => $components,
        'dependencies' => $dependencies,
    ];
}

/**
 * @param array<string, mixed> $package
 *
 * @return array<string, mixed>
 */
function sbomComponent(array $package): array
{
    $name      = requiredString($package, 'name', 'locked package');
    $version   = requiredString($package, 'version', $name);
    $nameParts = explode('/', $name, 2);
    $component = [
        'type' => 'library',
        'bom-ref' => packageUrl($name, $version),
        'group' => $nameParts[0],
        'name' => $nameParts[1] ?? $name,
        'version' => $version,
        'scope' => optionalBoolean($package, '_isDev') ? 'optional' : 'required',
        'purl' => packageUrl($name, $version),
    ];

    $licenses = optionalStringList($package, 'license');
    if ($licenses !== []) {
        $component['licenses'] = [];
        foreach ($licenses as $license) {
            $component['licenses'][] = ['license' => ['name' => $license]];
        }
    }

    $externalReferences = externalReferences($package);
    if ($externalReferences !== []) {
        $component['externalReferences'] = $externalReferences;
    }

    $properties = packageProperties($package);
    if ($properties !== []) {
        $component['properties'] = $properties;
    }

    return $component;
}

/**
 * @param array<string, mixed>  $package
 * @param array<string, string> $references
 *
 * @return array{ref: string, dependsOn: list<string>}
 */
function sbomDependency(array $package, array $references): array
{
    $requires  = optionalObject($package, 'require');
    $dependsOn = [];
    foreach ($requires as $name => $constraint) {
        if (! array_key_exists($name, $references)) {
            continue;
        }

        $dependsOn[] = $references[$name];
    }

    sort($dependsOn);

    return ['ref' => packageBomReference($package), 'dependsOn' => $dependsOn];
}

/**
 * @param array<string, mixed>  $rootConfig
 * @param array<string, string> $references
 *
 * @return list<string>
 */
function directDependencyReferences(array $rootConfig, array $references): array
{
    $requires = array_merge(optionalObject($rootConfig, 'require'), optionalObject($rootConfig, 'require-dev'));
    $result   = [];
    foreach ($requires as $name => $constraint) {
        if (! array_key_exists($name, $references)) {
            continue;
        }

        $result[] = $references[$name];
    }

    sort($result);

    return $result;
}

/** @param array<string, mixed> $package */
function packageBomReference(array $package): string
{
    $name    = requiredString($package, 'name', 'locked package');
    $version = requiredString($package, 'version', $name);

    return packageUrl($name, $version);
}

function packageUrl(string $name, string $version): string
{
    $nameParts = explode('/', $name, 2);
    $group     = rawurlencode($nameParts[0]);
    $package   = rawurlencode($nameParts[1] ?? $name);

    return sprintf('pkg:composer/%s/%s@%s', $group, $package, rawurlencode($version));
}

/**
 * @param array<string, mixed> $package
 *
 * @return list<array{type: string, url: string}>
 */
function externalReferences(array $package): array
{
    $result = [];
    foreach (['source' => 'vcs', 'dist' => 'distribution'] as $key => $type) {
        $details = optionalObject($package, $key);
        $url     = optionalString($details, 'url');
        if ($url === '') {
            continue;
        }

        $result[] = ['type' => $type, 'url' => $url];
    }

    return $result;
}

/**
 * @param array<string, mixed> $package
 *
 * @return list<array{name: string, value: string}>
 */
function packageProperties(array $package): array
{
    $properties = [];
    foreach (['source', 'dist'] as $key) {
        $details   = optionalObject($package, $key);
        $reference = optionalString($details, 'reference');
        if ($reference === '') {
            continue;
        }

        $properties[] = ['name' => 'composer:' . $key . '-reference', 'value' => $reference];
    }

    return $properties;
}

/**
 * @param array<string, mixed> $lock
 *
 * @return array{schemaVersion: int, composerLockSha256: string, packages: list<array<string, mixed>>}
 */
function packageContentManifest(array $lock, string $vendorPath, string $lockPath): array
{
    $installed = installedPackages($vendorPath);
    $packages  = [];

    foreach (lockedPackages($lock) as $package) {
        $name = requiredString($package, 'name', 'locked package');
        $path = installedPackagePath($installed, $name);

        $packages[] = [
            'name' => $name,
            'version' => requiredString($package, 'version', $name),
            'sourceReference' => packageReference($package),
            'sha256' => directoryDigest($path),
        ];
    }

    assertNoUnexpectedInstalledPackages($installed, $packages, $vendorPath);
    sortByReference($packages, 'name');

    $lockSha256 = hash_file('sha256', $lockPath);
    if ($lockSha256 === false) {
        throw new RuntimeException('Cannot calculate the Composer lock checksum.');
    }

    return [
        'schemaVersion' => 1,
        'composerLockSha256' => $lockSha256,
        'packages' => $packages,
    ];
}

/**
 * @param array<string, mixed> $lock
 *
 * @return list<array<string, mixed>>
 */
function lockedPackages(array $lock): array
{
    $packages    = packageList($lock, 'packages', false);
    $devPackages = packageList($lock, 'packages-dev', true);

    return array_merge($packages, $devPackages);
}

/**
 * @param array<string, mixed> $lock
 *
 * @return list<array<string, mixed>>
 */
function packageList(array $lock, string $key, bool $isDev): array
{
    $value = $lock[$key] ?? null;
    if (! is_array($value)) {
        throw new RuntimeException(sprintf('composer.lock has no valid %s list.', $key));
    }

    $packages = [];
    foreach ($value as $package) {
        if (! is_array($package)) {
            throw new RuntimeException(sprintf('composer.lock contains an invalid %s entry.', $key));
        }

        $package['_isDev'] = $isDev;
        $packages[]        = $package;
    }

    return $packages;
}

/** @return array<string, mixed> */
function installedPackages(string $vendorPath): array
{
    $installedFile = $vendorPath . '/composer/installed.php';
    if (! is_file($installedFile)) {
        throw new RuntimeException(sprintf('Composer installed metadata is unavailable: %s', $installedFile));
    }

    $installed = require $installedFile;
    if (! is_array($installed) || ! is_array($installed['versions'] ?? null)) {
        throw new RuntimeException('Composer installed metadata is invalid.');
    }

    return $installed['versions'];
}

/** @param array<string, mixed> $installed */
function installedPackagePath(array $installed, string $name): string
{
    $details = $installed[$name] ?? null;
    if (! is_array($details)) {
        throw new RuntimeException(sprintf('Locked package is not installed: %s', $name));
    }

    $path = $details['install_path'] ?? null;
    if (! is_string($path) || ! is_dir($path)) {
        throw new RuntimeException(sprintf('Installed package path is invalid: %s', $name));
    }

    $resolved = realpath($path);
    if ($resolved === false) {
        throw new RuntimeException(sprintf('Cannot resolve installed package path: %s', $name));
    }

    return $resolved;
}

/**
 * @param array<string, mixed>       $installed
 * @param list<array<string, mixed>> $packages
 */
function assertNoUnexpectedInstalledPackages(array $installed, array $packages, string $vendorPath): void
{
    $expected = [];
    foreach ($packages as $package) {
        $expected[$package['name']] = true;
    }

    $resolvedVendorPath = realpath($vendorPath);
    if ($resolvedVendorPath === false) {
        throw new RuntimeException(sprintf('Cannot resolve the Composer vendor directory: %s', $vendorPath));
    }

    foreach ($installed as $name => $details) {
        if (! is_array($details) || array_key_exists($name, $expected)) {
            continue;
        }

        $path = $details['install_path'] ?? null;
        if (! is_string($path) || ! is_dir($path)) {
            continue;
        }

        $resolvedPath = realpath($path);
        if ($resolvedPath === false || ! str_starts_with($resolvedPath, $resolvedVendorPath . '/')) {
            continue;
        }

        throw new RuntimeException(sprintf('Installed package is absent from composer.lock: %s', $name));
    }
}

function directoryDigest(string $path): string
{
    $entries  = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $item) {
        if (! $item instanceof SplFileInfo) {
            continue;
        }

        $itemPath = $item->getPathname();
        $relative = str_replace('\\', '/', substr($itemPath, strlen($path) + 1));
        if (is_link($itemPath)) {
            $target = readlink($itemPath);
            if ($target === false) {
                throw new RuntimeException(sprintf('Cannot read package symbolic link: %s', $itemPath));
            }

            $entries[] = "link\0" . $relative . "\0" . $target;
            continue;
        }

        if (! $item->isFile()) {
            continue;
        }

        $checksum = hash_file('sha256', $itemPath);
        if ($checksum === false) {
            throw new RuntimeException(sprintf('Cannot hash package file: %s', $itemPath));
        }

        $entries[] = "file\0" . $relative . "\0" . $checksum;
    }

    sort($entries);

    return hash('sha256', implode("\n", $entries));
}

/** @param array<string, mixed> $package */
function packageReference(array $package): string
{
    foreach (['source', 'dist'] as $key) {
        $reference = optionalString(optionalObject($package, $key), 'reference');
        if ($reference !== '') {
            return $reference;
        }
    }

    return optionalString($package, 'version') ?: 'unversioned';
}

/** @param array<string, mixed> $value */
function requiredString(array $value, string $key, string $context): string
{
    $result = optionalString($value, $key);
    if ($result === '') {
        throw new RuntimeException(sprintf('%s has no valid %s.', $context, $key));
    }

    return $result;
}

/** @param array<string, mixed> $value */
function optionalString(array $value, string $key): string
{
    $result = $value[$key] ?? '';

    return is_string($result) ? $result : '';
}

/** @param array<string, mixed> $value */
function optionalBoolean(array $value, string $key): bool
{
    return ($value[$key] ?? false) === true;
}

/**
 * @param array<string, mixed> $value
 *
 * @return array<string, mixed>
 */
function optionalObject(array $value, string $key): array
{
    $result = $value[$key] ?? [];

    return is_array($result) ? $result : [];
}

/**
 * @param array<string, mixed> $value
 *
 * @return list<string>
 */
function optionalStringList(array $value, string $key): array
{
    $result = $value[$key] ?? [];
    if (! is_array($result)) {
        return [];
    }

    $strings = [];
    foreach ($result as $item) {
        if (! is_string($item)) {
            continue;
        }

        $strings[] = $item;
    }

    return $strings;
}

/** @param list<array<string, mixed>> $items */
function sortByReference(array &$items, string $key): void
{
    $indexed = [];
    foreach ($items as $item) {
        $reference           = requiredString($item, $key, 'generated evidence');
        $indexed[$reference] = $item;
    }

    ksort($indexed);
    $items = array_values($indexed);
}

/** @param array<string, mixed> $evidence */
function writeEvidence(string $root, string $relativePath, array $evidence): void
{
    $path      = $root . '/' . $relativePath;
    $directory = dirname($path);
    if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
        throw new RuntimeException(sprintf('Cannot create evidence directory: %s', $directory));
    }

    if (file_put_contents($path, encodedEvidence($evidence)) === false) {
        throw new RuntimeException(sprintf('Cannot write supply-chain evidence: %s', $relativePath));
    }
}

/** @param array<string, mixed> $actual */
function verifyEvidence(string $root, string $relativePath, array $actual): void
{
    $path     = $root . '/' . $relativePath;
    $expected = file_get_contents($path);
    if ($expected === false) {
        throw new RuntimeException(sprintf('Supply-chain evidence is unavailable: %s', $relativePath));
    }

    if ($expected !== encodedEvidence($actual)) {
        throw new RuntimeException(
            sprintf('Supply-chain evidence is stale or package content changed: %s', $relativePath),
        );
    }
}

/** @param array<string, mixed> $evidence */
function encodedEvidence(array $evidence): string
{
    return json_encode(
        $evidence,
        JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    ) . PHP_EOL;
}
