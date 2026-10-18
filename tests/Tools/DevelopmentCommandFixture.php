<?php

declare(strict_types=1);

namespace Tests\Tools;

use PHPUnit\Framework\Assert;

use function bin2hex;
use function chmod;
use function copy;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function getenv;
use function is_file;
use function is_link;
use function microtime;
use function mkdir;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function random_bytes;
use function rmdir;
use function symlink;
use function sys_get_temp_dir;
use function unlink;
use function usleep;

use const PHP_BINARY;

final class DevelopmentCommandFixture
{
    private function __construct(private readonly string $root)
    {
    }

    public static function create(string|null $dotenv, int $commandDelayMicroseconds = 0): self
    {
        $root        = sys_get_temp_dir() . '/development-commands-' . bin2hex(random_bytes(8));
        $projectRoot = dirname(__DIR__, 2);
        mkdir($root . '/bin/dev', 0700, true);
        foreach (['require-dev.php', 'housekeeping.sh', 'message-viewer.php'] as $filename) {
            copy($projectRoot . '/bin/dev/' . $filename, $root . '/bin/dev/' . $filename);
        }

        symlink($projectRoot . '/vendor', $root . '/vendor');
        symlink($projectRoot . '/bin/dev/message-viewer', $root . '/bin/dev/message-viewer');
        if ($dotenv !== null) {
            file_put_contents($root . '/.env', $dotenv);
        }

        file_put_contents($root . '/bin/backendbase', '#!' . PHP_BINARY . "\n<?php\nusleep(" . $commandDelayMicroseconds . ");\nfile_put_contents(dirname(__DIR__) . '/started', 'yes');\nexit(42);\n");
        chmod($root . '/bin/backendbase', 0700);

        return new self($root);
    }

    /** @return array{status: int, output: string, started: bool} */
    public function run(string $filename, string|null $environment): array
    {
        $root      = $this->root;
        $variables = getenv();
        unset($variables['BACKENDBASE_ENV']);
        $command = $filename === 'housekeeping.sh' ? ['/bin/bash'] : [PHP_BINARY, '-d', 'variables_order=GPCS'];
        if ($environment !== null) {
            $command = ['/usr/bin/env', 'BACKENDBASE_ENV=' . $environment, ...$command];
        }

        $process = proc_open(
            [...$command, $root . '/bin/dev/' . $filename],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $root . '/stdout', 'w'], 2 => ['file', $root . '/stderr', 'w']],
            $pipes,
            sys_get_temp_dir(),
            $variables,
        );
        Assert::assertIsResource($process);
        try {
            $status = self::waitForProcess($process);

            return [
                'status' => $status,
                'output' => file_get_contents($root . '/stdout') . file_get_contents($root . '/stderr'),
                'started' => is_file($root . '/started'),
            ];
        } finally {
            proc_terminate($process, 9);
            proc_close($process);
        }
    }

    public function destroy(): void
    {
        foreach (['bin/dev/require-dev.php', 'bin/dev/housekeeping.sh', 'bin/dev/message-viewer.php', 'bin/dev/message-viewer', 'bin/backendbase', 'vendor', '.env', 'stdout', 'stderr', 'started'] as $filename) {
            $this->remove($filename);
        }

        rmdir($this->root . '/bin/dev');
        rmdir($this->root . '/bin');
        rmdir($this->root);
    }

    private function remove(string $filename): void
    {
        $path = $this->root . '/' . $filename;
        if (! is_file($path) && ! is_link($path)) {
            return;
        }

        unlink($path);
    }

    /** @param resource $process */
    private static function waitForProcess($process): int
    {
        $deadline = microtime(true) + 5;
        do {
            $status = proc_get_status($process);
            usleep(10_000);
        } while ($status['running'] && microtime(true) < $deadline);

        Assert::assertFalse($status['running'], 'The development command did not finish within five seconds.');

        return $status['exitcode'];
    }
}
