<?php

declare(strict_types=1);

namespace Tests\Tools;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function Backendbase\MessageViewer\parseSms;
use function Backendbase\MessageViewer\readProcess;
use function str_repeat;

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
        self::assertSame('firstsecond', readProcess([PHP_BINARY, '-r', 'echo "first"; fwrite(STDERR, "second");']));
    }

    public function testItStopsFailedProcessesWithoutExposingOutput(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot read SMS logs. Start Docker');
        readProcess([PHP_BINARY, '-r', 'fwrite(STDERR, "private diagnostic"); exit(1);']);
    }

    public function testItTerminatesTimedOutProcesses(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('timed out');
        readProcess([PHP_BINARY, '-r', 'sleep(10);'], 0.05);
    }

    public function testItRejectsOversizedProcessOutput(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('8 MiB');
        readProcess([PHP_BINARY, '-r', 'echo str_repeat("x", 8388609);']);
    }

    private static function line(string $message): string
    {
        return '2026-10-16T17:41:28.919817924Z 2026-10-16 17:41:28 INFO [sns] SNS SMS stub to +905551112233: ' . $message . "\n";
    }
}
