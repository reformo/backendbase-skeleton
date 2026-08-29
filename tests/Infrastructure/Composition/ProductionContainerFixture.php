<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Composition;

use Backendbase\Shared\Services\Settings;
use Backendbase\Shared\Settings as SettingsInterface;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

final class ProductionContainerFixture
{
    /** @param array<string, object> $overrides */
    public static function build(array $overrides, string $queueDriver = 'rabbitmq'): ContainerInterface
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions([
            SettingsInterface::class => new Settings(self::settings($queueDriver)),
        ]);
        $dependencies = require 'config/dependencies.php';
        $dependencies($containerBuilder);
        $containerBuilder->addDefinitions($overrides);

        return $containerBuilder->build();
    }

    /** @return array<string, mixed> */
    private static function settings(string $queueDriver): array
    {
        return [
            'env' => 'test',
            'readiness' => ['timeoutSeconds' => 2],
            'queue' => ['driver' => $queueDriver],
            'rabbitmq' => [
                'host' => '127.0.0.1',
                'port' => 5672,
                'user' => 'backendbase',
                'password' => 'backendbase',
                'vhost' => '/',
                'queue' => 'backendbase-queue',
                'exchange' => 'backendbase',
                'exchangeType' => 'direct',
                'deadLetterExchange' => 'backendbase.dead-letter',
                'deadLetterQueueSuffix' => '.dead-letter',
                'messageRetentionMilliseconds' => 604800000,
                'connectionTimeout' => 2,
                'readWriteTimeout' => 2,
                'heartbeat' => 30,
                'prefetchCount' => 1,
            ],
            'aws' => [
                'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
                'endpoint' => '',
                'region' => 'eu-central-1',
                'sns' => ['senderId' => 'Backendbase', 'smsType' => 'Transactional'],
                'sqs' => [
                    'continuous' => false,
                    'maxNumberOfMessages' => 10,
                    'queue' => 'events',
                    'queueUrl' => 'https://sqs.eu-central-1.amazonaws.com/123456789012/events',
                    'visibilityTimeout' => 30,
                    'waitTimeSeconds' => 20,
                ],
            ],
            'objectStore' => [
                'bucket' => 'assets',
                'cdnBaseUrl' => 'https://cdn.example.com',
                'credentials' => ['key' => 'test-key', 'secret' => 'test-secret'],
                'region' => 'eu-central-1',
                'endpoint' => '',
            ],
            'jwt' => [
                'alias' => 'test',
                'duration' => 'PT1H',
                'issuer' => 'backendbase',
                'permitted-for' => 'backendbase-client',
                'sign-key' => 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=',
            ],
        ];
    }
}
