<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters;

use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use Backendbase\Infrastructure\Adapters\Notification\SnsNotifier;
use Backendbase\Infrastructure\Adapters\Notification\StackNotifier;
use Backendbase\Infrastructure\Adapters\Queue\SqsQueue;
use Backendbase\Shared\Integrations\MessageConsumer;
use Backendbase\Shared\Integrations\MessagePublisher;
use Backendbase\Shared\Integrations\Notify;
use Backendbase\Shared\Services\Settings;
use Backendbase\Shared\Settings as SettingsInterface;
use DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function bin2hex;
use function is_dir;
use function is_file;
use function method_exists;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

final class AwsDependencyDefinitionsTest extends TestCase
{
    #[Test]
    public function itCompilesAndResolvesAwsQueueAndNotificationServices(): void
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions([
            SettingsInterface::class => new Settings(self::settings()),
        ]);
        $dependencies = require 'config/dependencies.php';
        $dependencies($containerBuilder);
        $containerBuilder->addDefinitions([LoggerInterface::class => new NullLogger()]);
        $containerClass       = 'AwsDefinitionsCompiledContainer' . bin2hex(random_bytes(8));
        $compilationDirectory = sys_get_temp_dir() . '/' . $containerClass;
        $compiledContainer    = $compilationDirectory . '/' . $containerClass . '.php';
        $containerBuilder->enableCompilation($compilationDirectory, $containerClass);

        try {
            $container = $containerBuilder->build();

            self::assertInstanceOf(SqsClient::class, $container->get(SqsClient::class));
            self::assertInstanceOf(SnsClient::class, $container->get(SnsClient::class));
            self::assertInstanceOf(SqsQueue::class, $container->get(MessagePublisher::class));
            self::assertInstanceOf(SqsQueue::class, $container->get(MessageConsumer::class));

            $notifier = $container->get(Notify::class);
            self::assertInstanceOf(StackNotifier::class, $notifier);
            self::assertInstanceOf(SnsNotifier::class, $container->get(SnsNotifier::class));
            self::assertFalse(method_exists(Notify::class, 'getClient'));
        } finally {
            if (is_file($compiledContainer)) {
                unlink($compiledContainer);
            }

            if (is_dir($compilationDirectory)) {
                rmdir($compilationDirectory);
            }
        }
    }

    /** @return array<string, mixed> */
    private static function settings(): array
    {
        return [
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
            'queue' => ['driver' => 'sqs'],
            'readiness' => ['timeoutSeconds' => 2],
        ];
    }
}
