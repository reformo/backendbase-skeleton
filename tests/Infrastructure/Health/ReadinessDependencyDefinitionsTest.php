<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Health;

use Aws\S3\S3ClientInterface;
use Aws\Sqs\SqsClient;
use Backendbase\Shared\Health\ReadinessChecks;
use Backendbase\Shared\Services\Settings;
use Backendbase\Shared\Settings as SettingsInterface;
use DI\ContainerBuilder;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Redislabs\Module\RedisJson\RedisJsonInterface;
use UnexpectedValueException;

use function array_key_exists;

final class ReadinessDependencyDefinitionsTest extends TestCase
{
    #[Test]
    public function itComposesChecksForRabbitMQAndSqs(): void
    {
        self::assertInstanceOf(ReadinessChecks::class, $this->checks('rabbitmq'));
        self::assertInstanceOf(ReadinessChecks::class, $this->checks('sqs'));
    }

    #[Test]
    public function itRejectsAnUnboundedReadinessTimeout(): void
    {
        $key         = 'BACKENDBASE_READINESS_TIMEOUT_SECONDS';
        $hadExisting = array_key_exists($key, $_ENV);
        $existing    = $_ENV[$key] ?? null;
        $_ENV[$key]  = '0';

        try {
            require 'config/autoload/global.php';
            self::fail('An unbounded readiness timeout must fail.');
        } catch (UnexpectedValueException $exception) {
            self::assertSame(
                'The readiness timeout must be between 0.1 and 10 seconds.',
                $exception->getMessage(),
            );
        } finally {
            unset($_ENV[$key]);
            if ($hadExisting) {
                $_ENV[$key] = $existing;
            }
        }
    }

    private function checks(string $driver): ReadinessChecks
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions([
            SettingsInterface::class => new Settings(self::settings($driver)),
            Connection::class => $this->createStub(Connection::class),
            RedisJsonInterface::class => $this->createStub(RedisJsonInterface::class),
            S3ClientInterface::class => $this->createStub(S3ClientInterface::class),
            SqsClient::class => $this->createStub(SqsClient::class),
        ]);
        $provider = require 'config/dependencies/readiness.php';
        $provider($containerBuilder);

        return $containerBuilder->build()->get(ReadinessChecks::class);
    }

    /** @return array<string, mixed> */
    private static function settings(string $driver): array
    {
        return [
            'readiness' => ['timeoutSeconds' => 2],
            'queue' => ['driver' => $driver],
            'rabbitmq' => [
                'host' => '127.0.0.1',
                'port' => 5672,
                'user' => 'backendbase',
                'password' => 'backendbase',
                'vhost' => '/',
            ],
            'aws' => ['sqs' => ['queue' => 'events', 'queueUrl' => 'https://example.com/events']],
            'objectStore' => ['bucket' => 'assets'],
        ];
    }
}
