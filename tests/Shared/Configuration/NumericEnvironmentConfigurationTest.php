<?php

declare(strict_types=1);

namespace Tests\Shared\Configuration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

use function array_key_exists;

final class NumericEnvironmentConfigurationTest extends TestCase
{
    #[Test]
    #[DataProvider('numericEnvironmentValues')]
    public function itRejectsMalformedNumericEnvironmentValues(
        string $key,
        string $configurationFile,
        string $expectedType,
    ): void {
        $hadExistingValue = array_key_exists($key, $_ENV);
        $existingValue    = $_ENV[$key] ?? null;
        $_ENV[$key]       = 'not-a-number';

        try {
            require $configurationFile;
            self::fail('Malformed numeric environment text must fail.');
        } catch (UnexpectedValueException $exception) {
            self::assertSame(
                'The ' . $key . ' environment value must be ' . $expectedType . '.',
                $exception->getMessage(),
            );
        } finally {
            unset($_ENV[$key]);
            if ($hadExistingValue) {
                $_ENV[$key] = $existingValue;
            }
        }
    }

    /** @return array<string, array{string, string, string}> */
    public static function numericEnvironmentValues(): array
    {
        return [
            'readiness timeout' => [
                'BACKENDBASE_READINESS_TIMEOUT_SECONDS',
                'config/autoload/global.php',
                'a finite number',
            ],
            'Redis port' => ['BACKENDBASE_REDIS_PORT', 'config/autoload/redis.global.php', 'an integer'],
            'RabbitMQ port' => [
                'BACKENDBASE_RABBITMQ_PORT',
                'config/autoload/rabbitmq.global.php',
                'an integer',
            ],
            'RabbitMQ connection timeout' => [
                'BACKENDBASE_RABBITMQ_CONNECTION_TIMEOUT',
                'config/autoload/rabbitmq.global.php',
                'a finite number',
            ],
            'RabbitMQ read-write timeout' => [
                'BACKENDBASE_RABBITMQ_READ_WRITE_TIMEOUT',
                'config/autoload/rabbitmq.global.php',
                'a finite number',
            ],
            'RabbitMQ heartbeat' => [
                'BACKENDBASE_RABBITMQ_HEARTBEAT',
                'config/autoload/rabbitmq.global.php',
                'an integer',
            ],
            'RabbitMQ prefetch count' => [
                'BACKENDBASE_RABBITMQ_PREFETCH_COUNT',
                'config/autoload/rabbitmq.global.php',
                'an integer',
            ],
            'SQS batch size' => [
                'AWS_SQS_MAX_NUMBER_OF_MESSAGES',
                'config/autoload/aws.global.php',
                'an integer',
            ],
            'SQS wait time' => [
                'AWS_SQS_WAIT_TIME_SECONDS',
                'config/autoload/aws.global.php',
                'an integer',
            ],
            'SQS visibility timeout' => [
                'AWS_SQS_VISIBILITY_TIMEOUT',
                'config/autoload/aws.global.php',
                'an integer',
            ],
        ];
    }
}
