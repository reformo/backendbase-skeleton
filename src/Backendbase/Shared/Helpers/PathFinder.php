<?php

declare(strict_types=1);

namespace Backendbase\Shared\Helpers;

use function array_map;
use function array_merge;
use function getcwd;
use function glob;
use function str_replace;

use const GLOB_NOSORT;
use const GLOB_ONLYDIR;

class PathFinder
{
    /** @return array<int, string> */
    public static function doctrineEntityPaths(): array
    {
        $projectRoot              = getcwd();
        $entityPaths              = glob($projectRoot . '/src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/Entity', GLOB_ONLYDIR) ?: [];
        $contextEntityPaths       = glob($projectRoot . '/src/Backendbase/Domain/*/Adapters/Persistence/Doctrine/Entity', GLOB_NOSORT);
        $nestedContextEntityPaths = glob($projectRoot . '/src/Backendbase/Domain/*/*/Adapters/Persistence/Doctrine/Entity', GLOB_NOSORT);

        if (! empty($contextEntityPaths)) {
            $entityPaths = array_merge($entityPaths, $contextEntityPaths);
        }

        if (! empty($nestedContextEntityPaths)) {
            $entityPaths = array_merge($entityPaths, $nestedContextEntityPaths);
        }

        return array_map(static fn ($path) => str_replace($projectRoot . '/', '', $path), $entityPaths);
    }
}
