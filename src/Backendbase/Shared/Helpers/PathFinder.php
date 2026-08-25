<?php

declare(strict_types=1);

namespace Backendbase\Shared\Helpers;

use function array_map;
use function array_merge;
use function getcwd;
use function glob;
use function str_replace;

use const GLOB_NOSORT;

class PathFinder
{
    /** @return array<int, string> */
    public static function doctrineEntityPaths(): array
    {
        $projectDir            = getcwd();
        $entityPaths           = [$projectDir . '/src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/Entity'];
        $entityPathsModules    = glob($projectDir . '/src/Backendbase/Domain/*/Adapters/Persistence/Doctrine/Entity', GLOB_NOSORT);
        $entityPathsSubModules = glob($projectDir . '/src/Backendbase/Domain/*/*/Adapters/Persistence/Doctrine/Entity', GLOB_NOSORT);

        if (! empty($entityPathsModules)) {
            $entityPaths = array_merge($entityPaths, $entityPathsModules);
        }

        if (! empty($entityPathsSubModules)) {
            $entityPaths = array_merge($entityPaths, $entityPathsSubModules);
        }

        return array_map(static fn ($path) => str_replace($projectDir . '/', '', $path), $entityPaths);
    }
}
