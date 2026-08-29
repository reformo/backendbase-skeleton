<?php

declare(strict_types=1);

use Aws\S3\S3ClientInterface;
use Aws\Sqs\SqsClient;
use Backendbase\Infrastructure\Health\MySQLReadinessCheck;
use Backendbase\Infrastructure\Health\ObjectStoreReadinessCheck;
use Backendbase\Infrastructure\Health\RabbitMQConnectionFactory;
use Backendbase\Infrastructure\Health\RabbitMQReadinessCheck;
use Backendbase\Infrastructure\Health\RedisReadinessCheck;
use Backendbase\Infrastructure\Health\SqsReadinessCheck;
use Backendbase\Shared\Health\DeferredReadinessCheck;
use Backendbase\Shared\Health\ReadinessCheck;
use Backendbase\Shared\Health\ReadinessChecks;
use Backendbase\Shared\Settings;
use DI\ContainerBuilder;
use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use Redislabs\Module\RedisJson\RedisJsonInterface;

return static function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
        ReadinessChecks::class => static function (ContainerInterface $container) {
            /** @return ReadinessCheck */
            $queueCheck = static function () use ($container) {
                $settings      = $container->get(Settings::class);
                $queueSettings = $settings->get('queue');
                $driver        = $queueSettings['driver'] ?? null;
                if ($driver === 'sqs') {
                    return new SqsReadinessCheck($container->get(SqsClient::class), $settings->get('aws')['sqs']);
                }

                if ($driver !== 'rabbitmq') {
                    throw new UnexpectedValueException('The queue driver must be rabbitmq or sqs.');
                }

                return new RabbitMQReadinessCheck($container->get(RabbitMQConnectionFactory::class));
            };

            return new ReadinessChecks([
                new DeferredReadinessCheck(
                    'mysql',
                    static fn () => new MySQLReadinessCheck($container->get(Connection::class)),
                ),
                new DeferredReadinessCheck(
                    'redis',
                    static fn () => new RedisReadinessCheck($container->get(RedisJsonInterface::class)),
                ),
                new DeferredReadinessCheck('queue', $queueCheck),
                new DeferredReadinessCheck(
                    'objectStore',
                    static function () use ($container): ReadinessCheck {
                        $objectStoreSettings = $container->get(Settings::class)->get('objectStore');

                        return new ObjectStoreReadinessCheck(
                            $container->get(S3ClientInterface::class),
                            $objectStoreSettings['bucket'],
                        );
                    },
                ),
            ]);
        },
    ]);
};
