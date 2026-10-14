<?php

declare(strict_types=1);

namespace Tests\Support\Services;

use Doctrine\DBAL\Connection;
use RuntimeException;

use function fclose;
use function fgets;
use function fwrite;
use function is_resource;
use function json_encode;
use function microtime;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function stream_set_timeout;
use function trim;
use function usleep;

use const JSON_THROW_ON_ERROR;
use const PHP_BINARY;

final class MySqlWorker
{
    /**
     * @param resource             $process
     * @param array<int, resource> $pipes
     */
    private function __construct(private $process, private array $pipes)
    {
    }

    /** @param list<string> $identities */
    public static function start(Connection $connection, string $operation, array $identities): self
    {
        $process = proc_open([PHP_BINARY, 'tests/Support/Services/mysql-worker.php'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('The MySQL test worker could not start.');
        }

        stream_set_timeout($pipes[1], 15);
        $worker = new self($process, $pipes);
        $worker->send(json_encode(['parameters' => $connection->getParams(), 'operation' => $operation, 'identities' => $identities], JSON_THROW_ON_ERROR));

        return $worker;
    }

    public function send(string $message): void
    {
        if (fwrite($this->pipes[0], $message . "\n") === false) {
            throw new RuntimeException('The MySQL test worker could not receive a command.');
        }
    }

    public function receive(): string
    {
        $line = fgets($this->pipes[1]);
        if ($line === false) {
            throw new RuntimeException('The MySQL test worker stopped or exceeded its 15-second deadline.');
        }

        return trim($line);
    }

    public function awaitLockWait(Connection $observer, int $connectionId): void
    {
        $deadline = microtime(true) + 10;
        do {
            $waiting = $observer->fetchOne('SELECT COUNT(*) FROM performance_schema.data_lock_waits w JOIN performance_schema.threads t ON t.THREAD_ID = w.REQUESTING_THREAD_ID WHERE t.PROCESSLIST_ID = ?', [$connectionId]);
            if ($waiting > 0) {
                return;
            }

            usleep(10000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('The competing MySQL connection did not wait for the expected row lock.');
    }

    public function close(): void
    {
        $status = proc_get_status($this->process);
        if ($status['running']) {
            proc_terminate($this->process);
        }

        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }

        proc_close($this->process);
    }
}
