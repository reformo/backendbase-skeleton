<?php

declare(strict_types=1);

namespace Tests\Shared\Helpers;

use Backendbase\Shared\Helpers\PathFinder;
use PHPUnit\Framework\TestCase;

use function chdir;
use function dirname;
use function getcwd;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;

final class PathFinderTest extends TestCase
{
    public function testEntityDiscoveryDoesNotRequireTheSharedEntityDirectory(): void
    {
        $originalDirectory = getcwd();
        self::assertIsString($originalDirectory);
        $testDirectory = sys_get_temp_dir() . '/backendbase-entity-paths-' . uniqid();
        $contextPath   = 'src/Backendbase/Domain/Example/Adapters/Persistence/Doctrine/Entity';
        $sharedPath    = 'src/Backendbase/Infrastructure/Adapters/Persistence/Doctrine/Entity';
        mkdir($testDirectory . '/' . $contextPath, 0777, true);

        try {
            chdir($testDirectory);
            self::assertSame([$contextPath], PathFinder::doctrineEntityPaths());

            mkdir($testDirectory . '/' . $sharedPath, 0777, true);
            self::assertSame([$sharedPath, $contextPath], PathFinder::doctrineEntityPaths());
        } finally {
            chdir($originalDirectory);
            $this->removeDirectoryTree($testDirectory, $contextPath);
            $this->removeDirectoryTree($testDirectory, $sharedPath);
            rmdir($testDirectory . '/src/Backendbase');
            rmdir($testDirectory . '/src');
            rmdir($testDirectory);
        }
    }

    private function removeDirectoryTree(string $testDirectory, string $entityPath): void
    {
        for ($directory = $testDirectory . '/' . $entityPath; $directory !== $testDirectory . '/src/Backendbase'; $directory = dirname($directory)) {
            if (! is_dir($directory)) {
                continue;
            }

            rmdir($directory);
        }
    }
}
