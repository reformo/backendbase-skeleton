<?php

declare(strict_types=1);

namespace Tests\Architecture\Support;

use function preg_match;
use function str_contains;
use function str_ends_with;

final class BoundedContextDependencies
{
    /** @return array<string, list<string>> */
    public static function businessLayers(): array
    {
        return ArchitectureDependencies::select(
            self::production(),
            static fn (string $file): bool => ! str_contains($file, '/Adapters/')
                && ! str_ends_with($file, '/ServiceProvider.php'),
        );
    }

    /** @return array<string, list<string>> */
    public static function domainCore(): array
    {
        return ArchitectureDependencies::select(
            self::production(),
            static fn (string $file): bool => preg_match(
                '#^src/Backendbase/Domain/[^/]+/(Domain|Authorization|Exception)/#',
                $file,
            ) === 1,
        );
    }

    /** @return list<string> */
    public static function isolationViolations(): array
    {
        return ArchitectureDependencies::violations(
            self::production(),
            static fn (string $file, string $dependency): bool => self::crossesContext($file, $dependency),
        );
    }

    /** @return array<string, list<string>> */
    private static function production(): array
    {
        return ArchitectureDependencies::select(
            ArchitectureDependencies::domain(),
            static fn (string $file): bool => ! str_contains($file, '/Tests/'),
        );
    }

    private static function crossesContext(string $file, string $dependency): bool
    {
        $fileContext       = self::fileContext($file);
        $dependencyContext = self::dependencyContext($dependency);
        if ($fileContext === null || $dependencyContext === null) {
            return false;
        }

        return $fileContext !== $dependencyContext;
    }

    private static function fileContext(string $file): string|null
    {
        return preg_match('#^src/Backendbase/Domain/([^/]+)/#', $file, $matches) === 1 ? $matches[1] : null;
    }

    private static function dependencyContext(string $dependency): string|null
    {
        return preg_match('#^Backendbase\\\\Domain\\\\([^\\\\]+)\\\\#', $dependency, $matches) === 1 ? $matches[1] : null;
    }
}
