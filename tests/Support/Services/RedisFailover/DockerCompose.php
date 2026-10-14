<?php

declare(strict_types=1);

namespace Tests\Support\Services\RedisFailover;

use RuntimeException;

use function fclose;
use function getenv;
use function is_resource;
use function microtime;
use function preg_match;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function stream_get_contents;
use function stream_set_blocking;
use function usleep;

final readonly class DockerCompose
{
    public function __construct(private string $project)
    {
        if (preg_match('/\Abackendbase-redis-failover-[0-9]+-[0-9]+\z/D', $project) !== 1) {
            throw new RuntimeException('Only a generated Redis failover project can be controlled.');
        }
    }

    public static function fromEnvironment(): self
    {
        return new self(getenv('BACKENDBASE_REDIS_FAILOVER_PROJECT') ?: '');
    }

    /** @param list<string> $arguments */
    public function run(array $arguments): string
    {
        $command = ['docker', 'compose', '--env-file', '/dev/null', '--project-name', $this->project, '--file', 'resources/testing/redis-failover/compose.yaml', ...$arguments];
        $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('The isolated Docker Compose command could not start.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        try {
            return self::awaitExit($process, $pipes);
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
    }

    /**
     * @param resource             $process
     * @param array<int, resource> $pipes
     */
    private static function awaitExit($process, array $pipes): string
    {
        $output   = '';
        $errors   = '';
        $deadline = microtime(true) + 50;
        while (! self::completed($process, $pipes, $output, $errors)) {
            self::checkDeadline($process, $deadline);
            usleep(100000);
        }

        return $output;
    }

    /**
     * @param resource             $process
     * @param array<int, resource> $pipes
     */
    private static function completed($process, array $pipes, string &$output, string &$errors): bool
    {
        $output .= stream_get_contents($pipes[1]);
        $errors .= stream_get_contents($pipes[2]);
        $status  = proc_get_status($process);
        if ($status['running']) {
            return false;
        }

        $output .= stream_get_contents($pipes[1]);
        $errors .= stream_get_contents($pipes[2]);
        self::assertSuccessful($status['exitcode'], $errors);

        return true;
    }

    private static function assertSuccessful(int $exitCode, string $errors): void
    {
        if ($exitCode !== 0) {
            throw new RuntimeException('Isolated Docker Compose failed: ' . $errors);
        }
    }

    /** @param resource $process */
    private static function checkDeadline($process, float $deadline): void
    {
        if (microtime(true) >= $deadline) {
            proc_terminate($process);

            throw new RuntimeException('The isolated Docker Compose command exceeded 50 seconds.');
        }
    }
}
