<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Console\Operations;

use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Infrastructure\Adapters\Console\Operations\SeedAdmin;
use Backendbase\Seeders\AdminSeeder;
use Backendbase\Shared\Exception\ResourceAccessForbidden;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function file_put_contents;
use function function_exists;
use function posix_geteuid;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class SeedAdminTest extends TestCase
{
    public function testPreviewDoesNotWriteOrReadAPassword(): void
    {
        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $tester    = new CommandTester($container->get(SeedAdmin::class));
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('No database record changed.', $tester->getDisplay());
        self::assertSame(0, $fixture->connection()->fetchOne('SELECT COUNT(*) FROM identity_account'));
    }

    public function testApplyUsesThePasswordFileAndDoesNotPrintItsContents(): void
    {
        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $path      = tempnam(sys_get_temp_dir(), 'admin-seed-test-');
        self::assertIsString($path);
        try {
            file_put_contents($path, 'initial-password-123');
            $command = new SeedAdmin($container->get(AdminSeeder::class), static function (): void {
            });
            $tester  = new CommandTester($command);
            self::assertSame(Command::SUCCESS, $tester->execute(['--apply' => true, '--password-file' => $path]));
            self::assertStringContainsString('Created Admin ', $tester->getDisplay());
            self::assertStringNotContainsString('initial-password-123', $tester->getDisplay());
            self::assertSame(Command::SUCCESS, $tester->execute(['--apply' => true, '--password-file' => $path]));
            self::assertStringContainsString('Admin already exists:', $tester->getDisplay());
        } finally {
            unlink($path);
        }
    }

    public function testApplyRequiresRootAuthorityAndAValidPasswordFile(): void
    {
        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $command   = new SeedAdmin($container->get(AdminSeeder::class), static function (): void {
            throw ResourceAccessForbidden::create('Root required.');
        });
        self::assertSame(Command::INVALID, new CommandTester($command)->execute(['--apply' => true]));
        $allowed = new SeedAdmin($container->get(AdminSeeder::class), static function (): void {
        });
        self::assertSame(Command::INVALID, new CommandTester($allowed)->execute(['--apply' => true]));
        self::assertSame(0, $fixture->connection()->fetchOne('SELECT COUNT(*) FROM identity_account'));
    }

    public function testDatabaseFailuresReturnFailureWithoutPrintingThePassword(): void
    {
        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $fixture->connection()->executeStatement('DROP TABLE identity_admin');
        $path = tempnam(sys_get_temp_dir(), 'admin-seed-failure-');
        self::assertIsString($path);
        try {
            file_put_contents($path, 'initial-password-123');
            $command = new SeedAdmin($container->get(AdminSeeder::class), static function (): void {
            });
            $tester  = new CommandTester($command);
            self::assertSame(Command::FAILURE, $tester->execute(['--apply' => true, '--password-file' => $path]));
            self::assertStringContainsString('No migration was applied.', $tester->getDisplay());
            self::assertStringNotContainsString('initial-password-123', $tester->getDisplay());
            self::assertSame(0, $fixture->connection()->fetchOne('SELECT COUNT(*) FROM identity_account'));
        } finally {
            unlink($path);
        }
    }

    public function testTheDefaultAuthorityRejectsAnUnprivilegedApply(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('This boundary case needs an unprivileged CLI.');
        }

        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $tester    = new CommandTester($container->get(SeedAdmin::class));
        self::assertSame(Command::INVALID, $tester->execute(['--apply' => true]));
        self::assertSame(0, $fixture->connection()->fetchOne('SELECT COUNT(*) FROM identity_account'));
    }
}
