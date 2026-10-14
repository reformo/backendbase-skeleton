<?php

declare(strict_types=1);

namespace Tests\Support\Services\RedisFailover;

use RuntimeException;

use function array_values;
use function count;
use function is_array;
use function is_string;
use function str_contains;

final readonly class FailoverTopology
{
    private function __construct(private DockerCompose $compose, private RedisNodes $nodes)
    {
    }

    public static function create(): self
    {
        $compose = DockerCompose::fromEnvironment();
        $compose->run(['down', '--volumes', '--remove-orphans', '--timeout', '2']);
        $compose->run(['up', '--detach', '--wait', '--wait-timeout', '40']);
        $nodes    = RedisNodes::inspect($compose);
        $topology = new self($compose, $nodes);
        $topology->ready();

        return $topology;
    }

    public function nodes(): RedisNodes
    {
        return $this->nodes;
    }

    public function control(string $action, string ...$arguments): void
    {
        $compose = $this->compose;
        $compose->run([$action, ...array_values($arguments)]);
    }

    public function plannedPromotion(): void
    {
        $nodes    = $this->nodes;
        $sentinel = $nodes->connect('sentinel-one');
        if ($sentinel->rawCommand('SENTINEL', 'FAILOVER', 'identity') === false) {
            throw new RuntimeException('Sentinel rejected the planned test failover.');
        }

        $this->awaitPromotion();
        Eventually::until(fn (): bool => $this->role('primary') === 'slave', 'old primary demotion');
    }

    public function awaitPromotion(): void
    {
        $nodes = $this->nodes;
        Eventually::until(static fn (): bool => $nodes->primaryName() === 'replica', 'Sentinel election');
        Eventually::until(fn (): bool => $this->role('replica') === 'master', 'replica promotion');
    }

    public function awaitNoQuorum(): void
    {
        $nodes    = $this->nodes;
        $sentinel = $nodes->connect('sentinel-one');
        Eventually::until(static function () use ($sentinel): bool {
            $sentinel->clearLastError();
            $result = $sentinel->rawCommand('SENTINEL', 'CKQUORUM', 'identity');
            $error  = $sentinel->getLastError();

            return $result === false && $error !== null && str_contains($error, 'NOQUORUM');
        }, 'lost Sentinel majority');
    }

    public function assertNoPromotion(): void
    {
        $nodes = $this->nodes;
        Eventually::throughout(fn (): bool => $nodes->primaryName() === 'primary' && $this->role('replica') === 'slave', 'no promotion without a majority');
    }

    public function disconnectReplication(): void
    {
        $nodes   = $this->nodes;
        $replica = $nodes->connect('replica');
        $replica->rawCommand('REPLICAOF', '127.0.0.1', '1');
        $primary = $nodes->connect('primary');
        Eventually::until(static function () use ($primary): bool {
            $info = $primary->info('replication');

            return is_array($info) && ($info['connected_slaves'] ?? -1) === 0;
        }, 'broken replication connection');
    }

    public function invalidateAccount(string $accountId): void
    {
        $nodes = $this->nodes;
        $nodes->invalidateAccount($accountId);
    }

    private function ready(): void
    {
        $nodes    = $this->nodes;
        $replica  = $nodes->connect('replica');
        $sentinel = $nodes->connect('sentinel-one');
        Eventually::until(static function () use ($replica, $sentinel): bool {
            $info     = $replica->info('replication');
            $peers    = $sentinel->rawCommand('SENTINEL', 'SENTINELS', 'identity');
            $replicas = $sentinel->rawCommand('SENTINEL', 'REPLICAS', 'identity');

            return is_array($info) && ($info['master_link_status'] ?? '') === 'up' && is_array($peers) && count($peers) === 2 && is_array($replicas) && count($replicas) === 1;
        }, 'replication link and Sentinel discovery');
    }

    private function role(string $service): string
    {
        $nodes  = $this->nodes;
        $client = $nodes->connect($service);
        $role   = $client->rawCommand('ROLE');

        if (! is_array($role) || ! is_string($role[0] ?? null)) {
            throw new RuntimeException('The isolated Redis node returned an invalid role.');
        }

        return $role[0];
    }
}
