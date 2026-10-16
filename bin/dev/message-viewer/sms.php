<?php

declare(strict_types=1);

namespace Backendbase\MessageViewer;

use RuntimeException;

use function array_reverse;
use function array_slice;
use function fclose;
use function is_resource;
use function microtime;
use function preg_match_all;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function stream_get_contents;
use function stream_set_blocking;
use function strlen;
use function usleep;
use function usort;

use const PREG_SET_ORDER;

/** @return list<array{timestamp: string, recipient: string, message: string}> */
function parseSms(string $logs): array
{
    preg_match_all(
        '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z) '
        . '\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} INFO \[sns\] '
        . 'SNS SMS stub to (\+[1-9][0-9]{1,14}): ([^\r\n]*)$/m',
        $logs,
        $matches,
        PREG_SET_ORDER,
    );
    $messages = [];
    foreach (array_reverse($matches) as $match) {
        $messages[] = ['timestamp' => $match[1], 'recipient' => $match[2], 'message' => $match[3]];
    }

    usort($messages, static fn (array $first, array $second): int => $second['timestamp'] <=> $first['timestamp']);

    return array_slice($messages, 0, 200);
}

/** @return list<array{timestamp: string, recipient: string, message: string}> */
function readSms(): array
{
    return parseSms(readProcess(['docker', 'logs', '--timestamps', '--tail', '2000', 'backendbase-ministack']));
}

/** @param non-empty-list<string> $command */
function readProcess(array $command, float $timeoutSeconds = 5): string
{
    $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (! is_resource($process)) {
        throw new RuntimeException('Cannot start the local Docker reader.');
    }

    foreach ($pipes as $pipe) {
        stream_set_blocking($pipe, false);
    }

    try {
        return collectProcess($process, $pipes, $timeoutSeconds);
    } finally {
        proc_terminate($process, 9);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }
}

/**
 * @param resource             $process
 * @param array<int, resource> $pipes
 */
function collectProcess($process, array $pipes, float $timeoutSeconds): string
{
    $deadline = microtime(true) + $timeoutSeconds;
    $content  = '';
    do {
        $content .= readProcessPipes($pipes, 8_388_609 - strlen($content));
        validateProcessRead(strlen($content), $deadline);
        $status = proc_get_status($process);
        usleep(10_000);
    } while ($status['running']);

    $content .= readProcessPipes($pipes, 8_388_609 - strlen($content));
    validateProcessRead(strlen($content), $deadline);
    if ($status['exitcode'] !== 0) {
        throw new RuntimeException('Cannot read SMS logs. Start Docker and run docker compose up -d ministack.');
    }

    return $content;
}

/** @param array<int, resource> $pipes */
function readProcessPipes(array $pipes, int $limit): string
{
    $content = '';
    foreach ($pipes as $pipe) {
        $content .= processChunk($pipe, $limit - strlen($content));
    }

    return $content;
}

/** @param resource $pipe */
function processChunk($pipe, int $limit): string
{
    $content = stream_get_contents($pipe, $limit);
    if ($content === false) {
        throw new RuntimeException('Cannot read the local Docker process output.');
    }

    return $content;
}

function validateProcessRead(int $length, float $deadline): void
{
    if ($length > 8_388_608) {
        throw new RuntimeException('MiniStack logs exceed the viewer limit of 8 MiB.');
    }

    if (microtime(true) > $deadline) {
        throw new RuntimeException('MiniStack log read timed out. Check Docker, then select Refresh.');
    }
}
