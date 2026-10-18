<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Configuration\Providers;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Infrastructure\Configuration\ApplicationRuntimeSettings;
use Backendbase\Infrastructure\Configuration\AwsSettings;
use Backendbase\Infrastructure\Configuration\IdentitySettings;
use Backendbase\Infrastructure\Configuration\NotificationSettings;
use Backendbase\Infrastructure\Configuration\QueueSettings;
use Backendbase\Shared\Configuration\HttpHeaderSettings;
use DI\ContainerBuilder;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

use function base64_encode;
use function is_array;
use function str_repeat;

final class ProviderLoadingTest extends TestCase
{
    #[DataProvider('apis')]
    public function testRenamedProvidersResolveConfiguredValuesThroughTheContainer(string $slug): void
    {
        $originalEnvironment = $_ENV;
        try {
            $_ENV      = [
                ...$_ENV,
                'CDN_BASE_URL' => 'https://cdn.example.com',
                'AWS_SQS_MAXIMUM_MESSAGE_COUNT' => '7',
                'BACKENDBASE_RABBITMQ_PREFETCH_COUNT' => '4',
                'BACKENDBASE_READINESS_TIMEOUT_SECONDS' => '1.5',
                'BACKENDBASE_IDENTITY_TOKEN_LIFETIME_SECONDS' => '1200',
                'BACKENDBASE_USER_JWT_ISSUER' => 'https://user.example.com',
                'BACKENDBASE_USER_JWT_AUDIENCE' => 'user-api',
                'BACKENDBASE_USER_JWT_KEY' => base64_encode(str_repeat('u', 32)),
                'BACKENDBASE_ADMIN_JWT_ISSUER' => 'https://admin.example.com',
                'BACKENDBASE_ADMIN_JWT_AUDIENCE' => 'system-admin-api',
                'BACKENDBASE_ADMIN_JWT_KEY' => base64_encode(str_repeat('a', 32)),
                'BACKENDBASE_SMS_DRIVER' => 'sns',
                'BACKENDBASE_EMAIL_DRIVER' => 'ses',
                'FIREBASE_PROJECT_ID' => 'provider-test',
                'FIREBASE_TIMEOUT_SECONDS' => '3.5',
            ];
            $container = self::container($slug);
            $runtime   = $container->get(ApplicationRuntimeSettings::class);
            self::assertSame('https://cdn.example.com', $runtime->cdnBaseUrl());
            self::assertSame('var/cache/' . $slug . '/routes.php', $runtime->routeCacheFile());
            $headers = $container->get(HttpHeaderSettings::class);
            self::assertStringContainsString('Idempotency-Key', $headers->allowedHeaders());
            self::assertConfiguredServices($container);
        } finally {
            $_ENV = $originalEnvironment;
        }
    }

    /** @return iterable<string, array{string}> */
    public static function apis(): iterable
    {
        yield 'example' => ['example-api'];
        yield 'user' => ['user-api'];
        yield 'system admin' => ['system-admin-api'];
    }

    private static function container(string $slug): ContainerInterface
    {
        $aggregator      = new ConfigAggregator([
            new PhpFileProvider('config/autoload/*'),
            new PhpFileProvider('config/' . $slug . '/*'),
        ]);
        $configuration   = $aggregator->getMergedConfig();
        $projectSettings = $configuration;
        unset($projectSettings['headers']);
        self::assertKebabCaseKeys($projectSettings);
        $builder  = new ContainerBuilder();
        $settings = require 'config/settings.php';
        $settings($builder, $configuration);
        $dependencies = require 'config/dependencies.php';
        $dependencies($builder);

        return $builder->build();
    }

    /** @param array<array-key, mixed> $values */
    private static function assertKebabCaseKeys(array $values): void
    {
        foreach ($values as $key => $value) {
            self::assertIsString($key);
            self::assertMatchesRegularExpression('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $key);
            self::assertKebabCaseKeys(is_array($value) ? $value : []);
        }
    }

    private static function assertConfiguredServices(ContainerInterface $container): void
    {
        $aws = $container->get(AwsSettings::class);
        $sqs = $aws->sqs();
        self::assertSame(7, $sqs->maximumMessageCount());
        $storage = $aws->objectStore();
        self::assertSame('https://cdn.example.com', $storage->cdnBaseUrl());
        $queue    = $container->get(QueueSettings::class);
        $topology = $queue->rabbitMqTopology();
        self::assertSame(4, $topology->prefetchCount());
        self::assertSame(1.5, $queue->readinessTimeoutSeconds());
        $identity = $container->get(IdentitySettings::class);
        self::assertSame(1200, $identity->tokenLifetimeSeconds());
        self::assertSame('user-api', $identity->realm(Realm::USER)['audience']);
        self::assertSame('system-admin-api', $identity->realm(Realm::ADMIN)['audience']);
        $notification = $container->get(NotificationSettings::class);
        $push         = $notification->push();
        self::assertSame('provider-test', $push->projectId());
        self::assertSame(3.5, $push->timeoutSeconds());
    }
}
