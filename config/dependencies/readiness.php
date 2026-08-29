<?php

declare(strict_types=1);

use Aws\S3\S3ClientInterface;
use Aws\Sqs\SqsClient;
use Backendbase\Infrastructure\Configuration\AwsSettings;
use Backendbase\Infrastructure\Configuration\Queue\QueueDriver;
use Backendbase\Infrastructure\Configuration\QueueSettings;
use Backendbase\Infrastructure\Health\MySQLReadinessCheck;
use Backendbase\Infrastructure\Health\ObjectStoreReadinessCheck;
use Backendbase\Infrastructure\Health\RabbitMQConnectionFactory;
use Backendbase\Infrastructure\Health\RabbitMQReadinessCheck;
use Backendbase\Infrastructure\Health\RedisReadinessCheck;
use Backendbase\Infrastructure\Health\SqsReadinessCheck;
use Backendbase\Shared\Health\DeferredReadinessCheck;
use Backendbase\Shared\Health\ReadinessCheck;
use Backendbase\Shared\Health\ReadinessChecks;
use DI\ContainerBuilder;
use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use Redislabs\Module\RedisJson\RedisJsonInterface;

return static function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
        ReadinessChecks::class => static function (ContainerInterface $container) {
            /** @return ReadinessCheck */
            $queueCheck = static function () use ($container) {
                $queueSettings = $container->get(QueueSettings::class);
                if ($queueSettings->driver() === QueueDriver::SQS) {
                    $awsSettings = $container->get(AwsSettings::class);
                    $client      = $container->get(SqsClient::class);
                    $sqsSettings = $awsSettings->sqs();

                    return new SqsReadinessCheck($client, $sqsSettings);
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
                        $awsSettings         = $container->get(AwsSettings::class);
                        $objectStoreSettings = $awsSettings->objectStore();
                        $client              = $container->get(S3ClientInterface::class);
                        $bucket              = $objectStoreSettings->bucket();

                        return new ObjectStoreReadinessCheck(
                            $client,
                            $bucket,
                        );
                    },
                ),
            ]);
        },
    ]);
};
