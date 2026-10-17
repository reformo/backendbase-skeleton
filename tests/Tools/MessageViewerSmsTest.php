<?php

declare(strict_types=1);

namespace Tests\Tools;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Backendbase\MessageViewer\parseSms;
use function Backendbase\MessageViewer\readProcess;
use function Backendbase\MessageViewer\readSms;
use function bin2hex;
use function chmod;
use function file_put_contents;
use function getenv;
use function mkdir;
use function proc_open;
use function putenv;
use function random_bytes;
use function rmdir;
use function str_repeat;
use function sys_get_temp_dir;
use function unlink;
use function var_export;

use const PHP_BINARY;

require_once 'bin/dev/message-viewer/sms.php';

final class MessageViewerSmsTest extends TestCase
{
    public function testItPreservesSmsTextAndExcludesUnrelatedLogs(): void
    {
        $logs     = 'Private unrelated log' . "\n" . self::line('First: doğrulama');
        $logs    .= 'SNS SMS stub to +905551112233: fake' . "\n";
        $logs    .= self::line('<img src=x onerror=alert(1)>');
        $logs    .= '2026-10-16T17:41:28.919817924Z continuation' . "\n";
        $messages = parseSms($logs);
        self::assertCount(2, $messages);
        self::assertSame('<img src=x onerror=alert(1)>', $messages[0]['message']);
        self::assertSame('First: doğrulama', $messages[1]['message']);
        self::assertSame('+905551112233', $messages[0]['recipient']);
        self::assertSame('2026-10-16T17:41:28.919817924Z', $messages[0]['timestamp']);
    }

    public function testItBoundsMessagesAndPreservesRepeatedSends(): void
    {
        self::assertCount(200, parseSms(str_repeat(self::line('same'), 205)));
        self::assertSame([], parseSms(''));
    }

    public function testItReadsBothProcessStreams(): void
    {
        self::assertSame('firstsecond', self::processOutput([PHP_BINARY, '-r', 'echo "first"; fwrite(STDERR, "second");']));
    }

    public function testItReadsSmsUsingTheFixedDockerCommand(): void
    {
        $directory    = sys_get_temp_dir() . '/message-viewer-' . bin2hex(random_bytes(8));
        $commandPath  = $directory . '/docker';
        $previousPath = getenv('PATH');
        $message      = 'Code: ; $(id) `id`';
        mkdir($directory);
        file_put_contents(
            $commandPath,
            '#!' . PHP_BINARY . "\n<?php\n"
            . 'if (array_slice($argv, 1) !== ["logs", "--timestamps", "--tail", "2000", "backendbase-ministack"]) { exit(1); }' . "\n"
            . 'fwrite(STDERR, ' . var_export(self::line($message), true) . ');',
        );
        chmod($commandPath, 0700);

        try {
            putenv('PATH=' . $directory);
            self::assertSame($message, readSms()[0]['message']);
        } finally {
            putenv($previousPath === false ? 'PATH' : 'PATH=' . $previousPath);
            unlink($commandPath);
            rmdir($directory);
        }
    }

    public function testItStopsFailedProcessesWithoutExposingOutput(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot read SMS logs. Start Docker');
        self::processOutput([PHP_BINARY, '-r', 'fwrite(STDERR, "private diagnostic"); exit(1);']);
    }

    public function testItTerminatesTimedOutProcesses(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('timed out');
        self::processOutput([PHP_BINARY, '-r', 'sleep(10);'], 0.05);
    }

    public function testItRejectsOversizedProcessOutput(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('8 MiB');
        self::processOutput([PHP_BINARY, '-r', 'echo str_repeat("x", 8388609);']);
    }

    /** @param non-empty-list<string> $command */
    private static function processOutput(array $command, float $timeoutSeconds = 5): string
    {
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);

        return readProcess($process, $pipes, $timeoutSeconds);
    }

    private static function line(string $message): string
    {
        return '2026-10-16T17:41:28.919817924Z 2026-10-16 17:41:28 INFO [sns] SNS SMS stub to +905551112233: ' . $message . "\n";
    }
}
