<?php

declare(strict_types=1);

namespace Tests\Support\Services\RedisFailover;

use Backendbase\Infrastructure\Configuration\RedisSettings;
use Backendbase\Shared\Services\Settings;
use DI\ContainerBuilder;
use Redis;
use Redislabs\Module\RedisJson\RedisJsonInterface;
use RuntimeException;

use function array_filter;
use function array_map;
use function array_search;
use function array_values;
use function explode;
use function filter_var;
use function is_array;
use function str_starts_with;
use function trim;

use const FILTER_VALIDATE_INT;

final readonly class RedisNodes
{
    /** @param array<string, array{address: string, port: int}> $nodes */
    private function __construct(private array $nodes)
    {
    }

    public static function inspect(DockerCompose $compose): self
    {
        $nodes = [];
        foreach (['primary', 'replica', 'sentinel-one', 'sentinel-two', 'sentinel-three'] as $service) {
            $nodes[$service] = self::inspectNode($compose, $service);
        }

        return new self($nodes);
    }

    public function connect(string $service): Redis
    {
        $client = new Redis();
        if (! $client->connect('127.0.0.1', $this->nodes[$service]['port'], 0.5, read_timeout: 0.5)) {
            throw new RuntimeException('The isolated Redis node is unavailable.');
        }

        return $client;
    }

    public function connection(string $service): RedisJsonInterface
    {
        $builder  = new ContainerBuilder();
        $provider = require 'config/dependencies/redis.php';
        $provider($builder);
        $builder->addDefinitions([
            RedisSettings::class => new RedisSettings(new Settings([
                'redis' => ['host' => '127.0.0.1', 'port' => $this->nodes[$service]['port'], 'username' => '', 'password' => '', 'tls' => false],
                'readiness' => ['timeoutSeconds' => 0.5],
            ])),
        ]);
        $container = $builder->build();
        $json      = $container->get(RedisJsonInterface::class);
        if ($json->raw('PING', 'redis-failover-probe') !== 'redis-failover-probe') {
            throw new RuntimeException('The production Redis JSON connection failed its first command.');
        }

        return $json;
    }

    public function primaryName(): string
    {
        $sentinel = $this->connect('sentinel-one');
        $address  = $sentinel->rawCommand('SENTINEL', 'GET-MASTER-ADDR-BY-NAME', 'identity');
        if (! is_array($address)) {
            throw new RuntimeException('Sentinel did not return a primary address.');
        }

        $name = array_search($address[0], array_map(static fn (array $node): string => $node['address'], $this->nodes), true);
        if ($name === false) {
            throw new RuntimeException('Sentinel selected a node outside the isolated topology.');
        }

        return $name;
    }

    public function invalidateAccount(string $accountId): void
    {
        $client = $this->connect('replica');
        foreach (['USER', 'ADMIN'] as $realm) {
            self::invalidateRealm($client, 'identity:{' . $realm . ':' . $accountId . '}:');
        }
    }

    private static function invalidateRealm(Redis $client, string $prefix): void
    {
        $cursor = null;
        do {
            $keys = $client->scan($cursor, $prefix . '*', 100);
            self::deleteAllowlists($client, $keys, $prefix);
        } while ($cursor !== 0);
    }

    /** @param array<int, string>|false $keys */
    private static function deleteAllowlists(Redis $client, array|false $keys, string $prefix): void
    {
        if ($keys === false || $keys === []) {
            return;
        }

        $allowlists = array_values(array_filter($keys, static fn (string $key): bool => str_starts_with($key, $prefix . 'jti:') || str_starts_with($key, $prefix . 'sid:')));
        if ($allowlists === []) {
            return;
        }

        $client->del($allowlists);
    }

    /** @return array{address: string, port: int} */
    private static function inspectNode(DockerCompose $compose, string $service): array
    {
        $containerPort = $service === 'primary' || $service === 'replica' ? '6379' : '26379';
        $published     = trim($compose->run(['port', $service, $containerPort]));
        $parts         = explode(':', $published);
        $port          = filter_var($parts[1] ?? '', FILTER_VALIDATE_INT);
        if ($parts[0] !== '127.0.0.1' || $port === false) {
            throw new RuntimeException('The failover topology must use a loopback test port: ' . $published);
        }

        $address = trim($compose->run(['exec', '-T', $service, 'hostname', '-i']));

        return ['address' => $address, 'port' => $port];
    }
}
