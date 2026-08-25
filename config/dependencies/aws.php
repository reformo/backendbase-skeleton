<?php

declare(strict_types=1);

use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use Backendbase\Infrastructure\Adapters\Aws\AwsClientConfigurationBuilder;
use Backendbase\Infrastructure\Adapters\Notification\SnsNotifier;
use Backendbase\Infrastructure\Adapters\Notification\StackNotifier;
use Backendbase\Infrastructure\Adapters\Queue\SqsQueue;
use Backendbase\Infrastructure\Adapters\S3Bucket;
use Backendbase\Shared\Integrations\BucketService;
use Backendbase\Shared\Integrations\Notify;
use Backendbase\Shared\Settings;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return static function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
        S3ClientInterface::class => static function (ContainerInterface $container) {
            $settings            = $container->get(Settings::class);
            $objectStoreSettings = $settings->get('objectStore');
            $readinessSettings   = $settings->get('readiness');
            $timeoutSeconds      = (float) ($readinessSettings['timeoutSeconds'] ?? 2);
            $credentials         = new Credentials(
                $objectStoreSettings['credentials']['key'],
                $objectStoreSettings['credentials']['secret'],
            );

            return new S3Client([
                'credentials' => $credentials,
                'http' => ['connect_timeout' => $timeoutSeconds, 'timeout' => $timeoutSeconds],
                'region' => $objectStoreSettings['region'],
                'version' => 'latest',
            ]);
        },
        BucketService::class => static function (ContainerInterface $container) {
            $objectStoreSettings = $container->get(Settings::class)->get('objectStore');
            $s3Client            = $container->get(S3ClientInterface::class);

            return new S3Bucket($s3Client, $objectStoreSettings['bucket'], $objectStoreSettings['cdnBaseUrl'] ?? null);
        },
        SqsClient::class => static function (ContainerInterface $container) {
            $settings    = $container->get(Settings::class);
            $awsSettings = $settings->get('aws');
            if (! is_array($awsSettings)) {
                throw new UnexpectedValueException('The AWS settings are invalid.');
            }

            $readinessSettings = $settings->get('readiness');
            $timeoutSeconds    = (float) ($readinessSettings['timeoutSeconds'] ?? 2);
            $configuration     = $container->get(AwsClientConfigurationBuilder::class);

            return new SqsClient($configuration->build($awsSettings, $timeoutSeconds));
        },
        SnsClient::class => static function (ContainerInterface $container) {
            $settings    = $container->get(Settings::class);
            $awsSettings = $settings->get('aws');
            if (! is_array($awsSettings)) {
                throw new UnexpectedValueException('The AWS settings are invalid.');
            }

            $readinessSettings = $settings->get('readiness');
            $timeoutSeconds    = (float) ($readinessSettings['timeoutSeconds'] ?? 2);
            $configuration     = $container->get(AwsClientConfigurationBuilder::class);

            return new SnsClient($configuration->build($awsSettings, $timeoutSeconds));
        },
        SqsQueue::class => static function (ContainerInterface $container) {
            $awsSettings = $container->get(Settings::class)->get('aws');
            if (! is_array($awsSettings) || ! is_array($awsSettings['sqs'] ?? null)) {
                throw new UnexpectedValueException('The AWS SQS settings are invalid.');
            }

            return new SqsQueue($container->get(SqsClient::class), $awsSettings['sqs']);
        },
        SnsNotifier::class => static function (ContainerInterface $container) {
            $awsSettings = $container->get(Settings::class)->get('aws');
            if (! is_array($awsSettings) || ! is_array($awsSettings['sns'] ?? null)) {
                throw new UnexpectedValueException('The AWS SNS settings are invalid.');
            }

            return new SnsNotifier($container->get(SnsClient::class), $awsSettings['sns']);
        },
        Notify::class => static function (ContainerInterface $container) {
            $notifier = new StackNotifier($container->get(LoggerInterface::class));
            $notifier->add($container->get(SnsNotifier::class));

            return $notifier;
        },
    ]);
};
