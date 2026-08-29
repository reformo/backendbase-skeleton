<?php

declare(strict_types=1);

namespace Backendbase\DocumentationLinks;

use DOMDocument;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

use function array_map;
use function array_pad;
use function array_slice;
use function array_unique;
use function count;
use function dirname;
use function explode;
use function file_exists;
use function file_get_contents;
use function fwrite;
use function html_entity_decode;
use function implode;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function parse_url;
use function pathinfo;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function rawurldecode;
use function sort;
use function sprintf;
use function str_starts_with;
use function strtolower;
use function trim;

use const PATHINFO_EXTENSION;
use const PHP_URL_PATH;
use const PREG_SET_ORDER;
use const STDERR;
use const STDOUT;

const DEFAULT_TARGETS = [
    'README.md',
    'CONTRIBUTING.md',
    'SECURITY.md',
    'CHANGELOG.md',
    'resources/docs',
    'resources/platform',
];

try {
    $root    = dirname(__DIR__);
    $targets = array_slice(commandArguments(), 1) ?: DEFAULT_TARGETS;
    $files   = documentationFiles($root, $targets);
    $errors  = [];

    foreach ($files as $file) {
        foreach (linksIn($file) as $link) {
            $error = validateLink($file, $link);
            if ($error === null) {
                continue;
            }

            $errors[] = $error;
        }
    }

    if ($errors !== []) {
        fwrite(STDERR, implode("\n", array_unique($errors)) . "\n");
        exit(1);
    }

    fwrite(STDOUT, sprintf('Documentation links passed for %d files.', count($files)) . "\n");
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

/**
 * @param list<string> $targets
 *
 * @return list<string>
 */
function documentationFiles(string $root, array $targets): array
{
    $files = [];
    foreach ($targets as $target) {
        $candidate = $root . '/' . $target;
        if (is_file($candidate) && isDocumentationFile($candidate)) {
            $files[] = $candidate;
            continue;
        }

        if (! is_dir($candidate)) {
            throw new RuntimeException(sprintf('Documentation target does not exist: %s', $target));
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($candidate));
        foreach ($iterator as $item) {
            if (! ($item instanceof SplFileInfo) || ! $item->isFile() || ! isDocumentationFile($item->getPathname())) {
                continue;
            }

            $files[] = $item->getPathname();
        }
    }

    $files = array_unique($files);
    sort($files);

    return $files;
}

function isDocumentationFile(string $file): bool
{
    return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['html', 'md'], true);
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

/** @return list<string> */
function linksIn(string $file): array
{
    $contents = file_get_contents($file);
    if ($contents === false) {
        throw new RuntimeException(sprintf('Cannot read documentation file: %s', $file));
    }

    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'html') {
        return htmlLinks($contents);
    }

    preg_match_all('~!?\[[^]]*]\(<([^>]+)>|!?\[[^]]*]\(([^\s)]+)|^\s*\[[^]]+]:\s*<?([^\s>]+)~m', $contents, $matches, PREG_SET_ORDER);

    return array_map(
        static fn (array $match): string => matchedLink($match),
        $matches,
    );
}

/** @param array<int, string> $match */
function matchedLink(array $match): string
{
    foreach ([1, 2, 3] as $index) {
        $value = $match[$index] ?? '';
        if ($value !== '') {
            return $value;
        }
    }

    throw new RuntimeException('A documentation link match has no target.');
}

/** @return list<string> */
function htmlLinks(string $contents): array
{
    $document = new DOMDocument();
    libxml_use_internal_errors(true);
    $loaded = $document->loadHTML($contents);
    libxml_clear_errors();
    if (! $loaded) {
        throw new RuntimeException('A documentation HTML file is invalid.');
    }

    $links = [];
    foreach (['a' => 'href', 'link' => 'href', 'img' => 'src', 'script' => 'src'] as $tag => $attribute) {
        foreach ($document->getElementsByTagName($tag) as $element) {
            if (! $element->hasAttribute($attribute)) {
                continue;
            }

            $links[] = $element->getAttribute($attribute);
        }
    }

    return $links;
}

function validateLink(string $source, string $link): string|null
{
    $link = trim(html_entity_decode($link));
    if ($link === '' || isExternalLink($link)) {
        return null;
    }

    [$location, $fragment] = array_pad(explode('#', $link, 2), 2, '');
    $path                  = parse_url(rawurldecode($location), PHP_URL_PATH);
    if ($path === false) {
        return sprintf('%s: invalid link %s', $source, $link);
    }

    $target = $path === '' ? $source : dirname($source) . '/' . $path;
    if (! file_exists($target)) {
        return sprintf('%s: missing link target %s', $source, $link);
    }

    if ($fragment !== '' && strtolower(pathinfo($target, PATHINFO_EXTENSION)) === 'html') {
        return validateHtmlFragment($source, $target, rawurldecode($fragment), $link);
    }

    return null;
}

function isExternalLink(string $link): bool
{
    return str_starts_with($link, '//') || preg_match('~^[a-z][a-z0-9+.-]*:~i', $link) === 1;
}

function validateHtmlFragment(string $source, string $target, string $fragment, string $link): string|null
{
    $contents = file_get_contents($target);
    if ($contents === false) {
        return sprintf('%s: cannot read link target %s', $source, $link);
    }

    $quoted = preg_quote($fragment, '~');
    if (preg_match('~\b(?:id|name)=["\']' . $quoted . '["\']~i', $contents) === 1) {
        return null;
    }

    return sprintf('%s: missing fragment %s', $source, $link);
}
