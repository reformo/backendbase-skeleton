<?php

declare(strict_types=1);

namespace Tests\Tools;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DevelopmentCommandsTest extends TestCase
{
    #[DataProvider('environments')]
    public function testItChecksTheEnvironmentBeforeRunningCommands(string|null $dotenv, string|null $environment, bool $allowed): void
    {
        $fixture = DevelopmentCommandFixture::create($dotenv);
        try {
            $guard = $fixture->run('require-dev.php', $environment);
            self::assertSame($allowed ? 0 : 1, $guard['status']);
            self::assertSame($allowed ? '' : "Commands under bin/dev require BACKENDBASE_ENV=dev.\n", $guard['output']);

            $viewer = $fixture->run('message-viewer.php', $environment);
            self::assertSame(1, $viewer['status']);
            self::assertSame($allowed ? "Start the local viewer with composer message-viewer.\n" : $guard['output'], $viewer['output']);

            $housekeeping = $fixture->run('housekeeping.sh', $environment);
            self::assertSame($allowed ? 42 : 1, $housekeeping['status']);
            self::assertSame($allowed, $housekeeping['started']);
            self::assertSame($guard['output'], $housekeeping['output']);
        } finally {
            $fixture->destroy();
        }
    }

    /** @return iterable<string, array{string|null, string|null, bool}> */
    public static function environments(): iterable
    {
        yield 'dotenv dev' => ["BACKENDBASE_ENV=\"dev\" # Local mode\n", null, true];
        yield 'process dev' => [null, 'dev', true];
        yield 'process overrides dotenv production' => ["BACKENDBASE_ENV=production\n", 'dev', true];
        yield 'dotenv production' => ["BACKENDBASE_ENV=production\n", null, false];
        yield 'missing environment' => [null, null, false];
        yield 'empty dotenv environment' => ["BACKENDBASE_ENV=\n", null, false];
        yield 'test' => ["BACKENDBASE_ENV=dev\n", 'test', false];
        yield 'ci' => ["BACKENDBASE_ENV=dev\n", 'ci', false];
        yield 'stage' => ["BACKENDBASE_ENV=dev\n", 'stage', false];
        yield 'production' => ["BACKENDBASE_ENV=dev\n", 'production', false];
        yield 'empty process environment' => ["BACKENDBASE_ENV=dev\n", '', false];
        yield 'development alias' => ["BACKENDBASE_ENV=dev\n", 'development', false];
        yield 'local alias' => ["BACKENDBASE_ENV=dev\n", 'local', false];
        yield 'unknown environment' => ["BACKENDBASE_ENV=dev\n", 'unknown', false];
    }

    public function testItRejectsMalformedSettingsWithoutExposingTheirContents(): void
    {
        $fixture = DevelopmentCommandFixture::create("PRIVATE_SETTING=private malformed value\n");
        try {
            $result = $fixture->run('housekeeping.sh', 'dev');
            self::assertSame(1, $result['status']);
            self::assertFalse($result['started']);
            self::assertSame("Cannot load development settings. Check the local .env file.\n", $result['output']);
        } finally {
            $fixture->destroy();
        }
    }
}
