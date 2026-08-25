<?php

declare(strict_types=1);

namespace Tests\Shared\Helpers;

use Backendbase\Shared\Helpers\PathFinder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function backendbaseEnv;
use function chdir;
use function dirname;
use function getcwd;
use function mkdir;
use function putenv;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;

final class HelpersTest extends TestCase
{
    #[Test]
    public function itFindsDoctrineEntityDirectories(): void
    {
        $paths = PathFinder::doctrineEntityPaths();

        self::assertContains(
            'src/Backendbase/Domain/ExampleBoundedContext/Adapters/Persistence/Doctrine/Entity',
            $paths,
        );
    }

    #[Test]
    public function itReadsEnvironmentValuesInPriorityOrder(): void
    {
        $key = 'BACKENDBASE_TEST_ENV_HELPER';
        unset($_ENV[$key]);
        putenv($key);
        self::assertSame('default', backendbaseEnv($key, 'default'));

        putenv($key . '=process');
        self::assertSame('process', backendbaseEnv($key));

        $_ENV[$key] = 'environment';
        self::assertSame('environment', backendbaseEnv($key));

        unset($_ENV[$key]);
        putenv($key);
    }

    #[Test]
    public function itFindsNestedModuleEntityDirectories(): void
    {
        $originalDirectory = getcwd();
        self::assertIsString($originalDirectory);
        $testDirectory = sys_get_temp_dir() . '/backendbase-path-finder-' . uniqid();
        $entityPath    = 'src/Backendbase/Domain/Parent/Child/Adapters/Persistence/Doctrine/Entity';
        mkdir($testDirectory . '/' . $entityPath, 0777, true);

        try {
            chdir($testDirectory);

            self::assertContains($entityPath, PathFinder::doctrineEntityPaths());
        } finally {
            chdir($originalDirectory);
            for ($directory = $testDirectory . '/' . $entityPath; $directory !== $testDirectory; $directory = dirname($directory)) {
                rmdir($directory);
            }

            rmdir($testDirectory);
        }
    }
}
