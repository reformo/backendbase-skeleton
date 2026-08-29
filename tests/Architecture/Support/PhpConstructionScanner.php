<?php

declare(strict_types=1);

namespace Tests\Architecture\Support;

use FilesystemIterator;
use PhpParser\Error;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

use function array_unique;
use function array_values;
use function file_get_contents;
use function ksort;
use function sort;
use function str_replace;
use function usort;

final class PhpConstructionScanner
{
    /** @return array<string, list<string>> */
    public static function constructionsByFile(string $projectRoot, string $relativeDirectory): array
    {
        $parser        = new ParserFactory()->createForNewestSupportedVersion();
        $directory     = $projectRoot . '/' . $relativeDirectory;
        $constructions = [];

        foreach (self::phpFiles($directory) as $file) {
            $relativePath                 = str_replace($projectRoot . '/', '', $file->getPathname());
            $constructions[$relativePath] = self::constructions($parser, $file->getPathname(), $relativePath);
        }

        ksort($constructions);

        return $constructions;
    }

    /** @return list<SplFileInfo> */
    private static function phpFiles(string $directory): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );
        $files    = [];

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $files[] = $file;
        }

        usort($files, static fn (SplFileInfo $left, SplFileInfo $right): int => $left->getPathname() <=> $right->getPathname());

        return $files;
    }

    /** @return list<string> */
    private static function constructions(Parser $parser, string $path, string $relativePath): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Cannot read ' . $relativePath . '.');
        }

        try {
            $statements = $parser->parse($contents);
        } catch (Error $exception) {
            throw new RuntimeException('Cannot parse ' . $relativePath . '.', 0, $exception);
        }

        if ($statements === null) {
            return [];
        }

        $traverser = new NodeTraverser(new NameResolver(null, ['replaceNodes' => false]));
        $nodes     = $traverser->traverse($statements);
        $finder    = new NodeFinder();
        $result    = [];

        foreach ($finder->findInstanceOf($nodes, New_::class) as $construction) {
            if (! $construction->class instanceof Name) {
                continue;
            }

            $result[] = 'new ' . self::resolvedName($construction->class);
        }

        foreach ($finder->findInstanceOf($nodes, StaticCall::class) as $call) {
            if (! $call->class instanceof Name || ! $call->name instanceof Identifier) {
                continue;
            }

            $result[] = self::resolvedName($call->class) . '::' . $call->name->toString();
        }

        $result = array_values(array_unique($result));
        sort($result);

        return $result;
    }

    private static function resolvedName(Name $name): string
    {
        $resolvedName = $name->getAttribute('resolvedName');

        return $resolvedName instanceof Name ? $resolvedName->toString() : $name->toString();
    }
}
