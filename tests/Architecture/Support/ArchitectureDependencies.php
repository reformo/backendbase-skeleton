<?php

declare(strict_types=1);

namespace Tests\Architecture\Support;

use function array_filter;
use function array_push;
use function dirname;
use function sort;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

use const ARRAY_FILTER_USE_BOTH;

final class ArchitectureDependencies
{
    /** @return array<string, list<string>> */
    public static function domain(): array
    {
        return PhpDependencyScanner::dependenciesByFile(self::projectRoot(), 'src/Backendbase/Domain');
    }

    /** @return array<string, list<string>> */
    public static function shared(): array
    {
        return PhpDependencyScanner::dependenciesByFile(self::projectRoot(), 'src/Backendbase/Shared');
    }

    /** @return array<string, list<string>> */
    public static function source(): array
    {
        return PhpDependencyScanner::dependenciesByFile(self::projectRoot(), 'src/Backendbase');
    }

    public static function className(string $file): string
    {
        $relativeClass = substr($file, strlen('src/Backendbase/'));
        if (! str_ends_with($relativeClass, '.php')) {
            return 'Backendbase\\Invalid';
        }

        return 'Backendbase\\' . str_replace('/', '\\', substr($relativeClass, 0, -4));
    }

    /**
     * @param array<string, list<string>> $dependenciesByFile
     * @param callable(string): bool      $isIncluded
     *
     * @return array<string, list<string>>
     */
    public static function select(array $dependenciesByFile, callable $isIncluded): array
    {
        return array_filter(
            $dependenciesByFile,
            static fn (array $dependencies, string $file): bool => $isIncluded($file),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @param array<string, list<string>>    $dependenciesByFile
     * @param callable(string, string): bool $isForbidden
     *
     * @return list<string>
     */
    public static function violations(array $dependenciesByFile, callable $isForbidden): array
    {
        $violations = [];

        foreach ($dependenciesByFile as $file => $dependencies) {
            array_push($violations, ...self::fileViolations($file, $dependencies, $isForbidden));
        }

        sort($violations);

        return $violations;
    }

    /**
     * @param array<string, list<string>> $dependenciesByFile
     * @param list<string>                $prefixes
     *
     * @return list<string>
     */
    public static function prefixViolations(array $dependenciesByFile, array $prefixes): array
    {
        return self::violations(
            $dependenciesByFile,
            static fn (string $file, string $dependency): bool => self::startsWithOne($dependency, $prefixes),
        );
    }

    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @param list<string>                   $dependencies
     * @param callable(string, string): bool $isForbidden
     *
     * @return list<string>
     */
    private static function fileViolations(string $file, array $dependencies, callable $isForbidden): array
    {
        $violations = [];

        foreach ($dependencies as $dependency) {
            if (! $isForbidden($file, $dependency)) {
                continue;
            }

            $violations[] = $file . ' depends on ' . $dependency;
        }

        return $violations;
    }

    /** @param list<string> $prefixes */
    private static function startsWithOne(string $dependency, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($dependency, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
