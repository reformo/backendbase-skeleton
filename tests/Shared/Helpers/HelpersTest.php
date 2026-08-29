<?php

declare(strict_types=1);

namespace Tests\Shared\Helpers;

use Backendbase\Shared\Helpers\PathFinder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

use function backendbaseEnv;
use function backendbaseFloatEnvironmentValue;
use function backendbaseIntegerEnvironmentValue;
use function chdir;
use function dirname;
use function getcwd;
use function mkdir;
use function putenv;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;

use const NAN;

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
    public function itParsesTypedNumericEnvironmentValues(): void
    {
        $integerKey = 'BACKENDBASE_TEST_INTEGER_ENV_HELPER';
        $floatKey   = 'BACKENDBASE_TEST_FLOAT_ENV_HELPER';
        unset($_ENV[$integerKey], $_ENV[$floatKey]);
        putenv($integerKey);
        putenv($floatKey);

        self::assertSame(12, backendbaseIntegerEnvironmentValue($integerKey, 12));
        self::assertSame(1.5, backendbaseFloatEnvironmentValue($floatKey, 1.5));

        putenv($integerKey . '=42');
        putenv($floatKey . '=2.5');
        self::assertSame(42, backendbaseIntegerEnvironmentValue($integerKey, 12));
        self::assertSame(2.5, backendbaseFloatEnvironmentValue($floatKey, 1.5));

        $_ENV[$floatKey] = 3;
        self::assertSame(3.0, backendbaseFloatEnvironmentValue($floatKey, 1.5));

        unset($_ENV[$floatKey]);
        putenv($integerKey);
        putenv($floatKey);
    }

    #[Test]
    public function itRejectsInvalidNumericEnvironmentValues(): void
    {
        $integerKey        = 'BACKENDBASE_TEST_INTEGER_ENV_HELPER';
        $floatKey          = 'BACKENDBASE_TEST_FLOAT_ENV_HELPER';
        $_ENV[$integerKey] = 'not-an-integer';
        $_ENV[$floatKey]   = NAN;

        try {
            backendbaseIntegerEnvironmentValue($integerKey, 12);
            self::fail('Invalid integer text must fail.');
        } catch (UnexpectedValueException $exception) {
            self::assertSame(
                'The BACKENDBASE_TEST_INTEGER_ENV_HELPER environment value must be an integer.',
                $exception->getMessage(),
            );
        }

        try {
            backendbaseFloatEnvironmentValue($floatKey, 1.5);
            self::fail('A non-finite number must fail.');
        } catch (UnexpectedValueException $exception) {
            self::assertSame(
                'The BACKENDBASE_TEST_FLOAT_ENV_HELPER environment value must be a finite number.',
                $exception->getMessage(),
            );
        } finally {
            unset($_ENV[$integerKey], $_ENV[$floatKey]);
        }
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
