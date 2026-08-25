<?php

declare(strict_types=1);

namespace Tests\Infrastructure\UseCase\Console\GoodHousekeeping;

use Backendbase\Infrastructure\UseCase\Console\GoodHousekeeping\ClearCache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

use function chdir;
use function file_exists;
use function file_put_contents;
use function getcwd;
use function mkdir;
use function rmdir;
use function symlink;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class ClearCacheTest extends TestCase
{
    #[Test]
    public function itDeletesFilesOnlyFromTheProjectCacheDirectory(): void
    {
        $originalDirectory = getcwd();
        self::assertIsString($originalDirectory);
        $testDirectory = sys_get_temp_dir() . '/backendbase-clear-cache-' . uniqid();
        mkdir($testDirectory . '/var/cache/nested', 0777, true);
        file_put_contents($testDirectory . '/var/cache/cache.php', 'cache');
        file_put_contents($testDirectory . '/var/cache/nested/cache.php', 'cache');

        try {
            chdir($testDirectory);
            $exitCode = (new CommandTester(new ClearCache()))->execute([]);

            self::assertSame(1, $exitCode);
            self::assertFalse(file_exists($testDirectory . '/var/cache/cache.php'));
            self::assertFalse(file_exists($testDirectory . '/var/cache/nested'));
        } finally {
            chdir($originalDirectory);
            rmdir($testDirectory . '/var/cache');
            rmdir($testDirectory . '/var');
            rmdir($testDirectory);
        }
    }

    #[Test]
    public function itSkipsGitAndExternalSymlinkTargets(): void
    {
        $originalDirectory = getcwd();
        self::assertIsString($originalDirectory);
        $testDirectory = sys_get_temp_dir() . '/backendbase-clear-cache-' . uniqid();
        $externalFile  = sys_get_temp_dir() . '/backendbase-external-' . uniqid();
        mkdir($testDirectory . '/var/cache/.git', 0777, true);
        file_put_contents($testDirectory . '/var/cache/.git/keep', 'keep');
        file_put_contents($externalFile, 'external');
        symlink($externalFile, $testDirectory . '/var/cache/external-link');
        symlink(
            $testDirectory . '/missing-target',
            $testDirectory . '/var/cache/broken-link',
        );

        try {
            chdir($testDirectory);
            $exitCode = (new CommandTester(new ClearCache()))->execute([]);

            self::assertSame(1, $exitCode);
            self::assertTrue(file_exists($externalFile));
            self::assertTrue(file_exists($testDirectory . '/var/cache/.git/keep'));
        } finally {
            chdir($originalDirectory);
            unlink($testDirectory . '/var/cache/broken-link');
            unlink($testDirectory . '/var/cache/external-link');
            unlink($testDirectory . '/var/cache/.git/keep');
            rmdir($testDirectory . '/var/cache/.git');
            rmdir($testDirectory . '/var/cache');
            rmdir($testDirectory . '/var');
            rmdir($testDirectory);
            unlink($externalFile);
        }
    }

    #[Test]
    public function itRejectsAnUnresolvableCurrentDirectory(): void
    {
        $originalDirectory = getcwd();
        self::assertIsString($originalDirectory);
        $testDirectory = sys_get_temp_dir() . '/backendbase-missing-cwd-' . uniqid();
        mkdir($testDirectory);
        $tester = new CommandTester(new ClearCache());

        try {
            chdir($testDirectory);
            rmdir($testDirectory);
            $this->expectException(RuntimeException::class);

            $tester->execute([]);
        } finally {
            chdir($originalDirectory);
        }
    }
}
