<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Composition;

use Backendbase\Infrastructure\Adapters\CQRS\RegistryHandlerResolver;
use Backendbase\Shared\Services\Settings;
use Backendbase\Shared\Settings as SettingsInterface;
use DI\ContainerBuilder;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;
use Psr\Container\ContainerInterface;

use function array_replace_recursive;

final class ProductionContainerFixture
{
    /**
     * @param array<string, mixed>  $configuration
     * @param array<string, object> $overrides
     */
    public static function configured(array $configuration, array $overrides = []): ContainerInterface
    {
        $aggregator    = new ConfigAggregator([
            new PhpFileProvider('config/autoload/*'),
            new PhpFileProvider('config/example-api/*'),
        ]);
        $defaults      = $aggregator->getMergedConfig();
        $configuration = array_replace_recursive($defaults, self::settings('rabbitmq'), $configuration);
        $builder       = new ContainerBuilder();
        $settings      = require 'config/settings.php';
        $settings($builder, $configuration);
        $dependencies = require 'config/dependencies.php';
        $dependencies($builder);
        $builder->addDefinitions($overrides);

        return $builder->build();
    }

    /** @param array<string, object> $overrides */
    public static function build(array $overrides, string $queueDriver = 'rabbitmq', string $resolverClass = RegistryHandlerResolver::class): ContainerInterface
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions([
            SettingsInterface::class => new Settings(self::settings($queueDriver)),
        ]);
        $dependencies = require 'config/dependencies.php';
        $dependencies($containerBuilder, $resolverClass);
        $containerBuilder->addDefinitions($overrides);

        return $containerBuilder->build();
    }

    /** @return array<string, mixed> */
    private static function settings(string $queueDriver): array
    {
        return [
            'env' => 'test',
            'readiness' => ['timeout-seconds' => 2],
            'queue' => ['driver' => $queueDriver],
            'rabbitmq' => [
                'host' => '127.0.0.1',
                'port' => 5672,
                'user' => 'backendbase',
                'password' => 'backendbase',
                'vhost' => '/',
                'queue' => 'backendbase-queue',
                'exchange' => 'backendbase',
                'exchange-type' => 'direct',
                'dead-letter-exchange' => 'backendbase.dead-letter',
                'dead-letter-queue-suffix' => '.dead-letter',
                'message-retention-milliseconds' => 604800000,
                'connection-timeout' => 2,
                'read-write-timeout' => 2,
                'heartbeat' => 30,
                'prefetch-count' => 1,
            ],
            'aws' => [
                'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
                'endpoint' => '',
                'region' => 'eu-central-1',
                'sns' => ['sender-id' => 'Backendbase', 'sms-type' => 'Transactional'],
                'sqs' => [
                    'continuous' => false,
                    'maximum-message-count' => 10,
                    'queue' => 'events',
                    'queue-url' => 'https://sqs.eu-central-1.amazonaws.com/123456789012/events',
                    'visibility-timeout-seconds' => 30,
                    'wait-time-seconds' => 20,
                ],
            ],
            'object-store' => [
                'bucket' => 'assets',
                'cdn-base-url' => 'https://cdn.example.com',
                'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
                'region' => 'eu-central-1',
                'endpoint' => '',
            ],
        ];
    }
}
