<?php

declare(strict_types=1);

use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use Aws\SesV2\SesV2Client;
use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use Backendbase\Infrastructure\Adapters\Aws\AwsClientConfigurationBuilder;
use Backendbase\Infrastructure\Adapters\Notification\SnsNotifier;
use Backendbase\Infrastructure\Adapters\Queue\SqsQueue;
use Backendbase\Infrastructure\Adapters\Queue\SqsTransport;
use Backendbase\Infrastructure\Adapters\S3Bucket;
use Backendbase\Infrastructure\Configuration\AwsSettings;
use Backendbase\Shared\Integrations\BucketService;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return static function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
        S3ClientInterface::class => static function (ContainerInterface $container) {
            $settings               = $container->get(AwsSettings::class);
            $objectStoreSettings    = $settings->objectStore();
            $objectStoreCredentials = $objectStoreSettings->credentials();
            $accessKey              = $objectStoreCredentials->accessKey();
            $secretKey              = $objectStoreCredentials->secretKey();
            $timeoutSeconds         = $settings->readinessTimeoutSeconds();
            $region                 = $objectStoreSettings->region();
            $endpoint               = $objectStoreSettings->endpoint();
            $credentials            = new Credentials(
                $accessKey,
                $secretKey,
            );

            return new S3Client([
                'credentials' => $credentials,
                ...($endpoint === '' ? [] : [
                    'endpoint' => $endpoint,
                    'use_path_style_endpoint' => true,
                ]),
                'http' => [
                    'connect_timeout' => $timeoutSeconds,
                    'timeout' => $timeoutSeconds,
                ],
                'region' => $region,
                'version' => 'latest',
            ]);
        },
        BucketService::class => static function (ContainerInterface $container) {
            $awsSettings         = $container->get(AwsSettings::class);
            $objectStoreSettings = $awsSettings->objectStore();
            $s3Client            = $container->get(S3ClientInterface::class);
            $bucket              = $objectStoreSettings->bucket();
            $cdnBaseUrl          = $objectStoreSettings->cdnBaseUrl();

            return new S3Bucket(
                $s3Client,
                $bucket,
                $cdnBaseUrl,
            );
        },
        SqsClient::class => static function (ContainerInterface $container) {
            $settings       = $container->get(AwsSettings::class);
            $configuration  = $container->get(AwsClientConfigurationBuilder::class);
            $clientSettings = $settings->client();
            $timeoutSeconds = $settings->readinessTimeoutSeconds();

            return new SqsClient($configuration->build(
                $clientSettings,
                $timeoutSeconds,
            ));
        },
        SnsClient::class => static function (ContainerInterface $container) {
            $settings       = $container->get(AwsSettings::class);
            $configuration  = $container->get(AwsClientConfigurationBuilder::class);
            $clientSettings = $settings->client();
            $timeoutSeconds = $settings->readinessTimeoutSeconds();

            return new SnsClient($configuration->build(
                $clientSettings,
                $timeoutSeconds,
            ));
        },
        SqsTransport::class => static function (ContainerInterface $container) {
            $client = $container->get(SqsClient::class);
            $logger = $container->get(LoggerInterface::class);

            return new SqsTransport($client, $logger);
        },
        SqsQueue::class => static function (ContainerInterface $container) {
            $settings    = $container->get(AwsSettings::class);
            $transport   = $container->get(SqsTransport::class);
            $sqsSettings = $settings->sqs();

            return new SqsQueue($transport, $sqsSettings);
        },
        SnsNotifier::class => static function (ContainerInterface $container) {
            $settings    = $container->get(AwsSettings::class);
            $client      = $container->get(SnsClient::class);
            $snsSettings = $settings->sns();

            return new SnsNotifier($client, $snsSettings);
        },
        SesV2Client::class => static function (ContainerInterface $container) {
            $settings       = $container->get(AwsSettings::class);
            $configuration  = $container->get(AwsClientConfigurationBuilder::class);
            $clientSettings = $settings->client();
            $timeoutSeconds = $settings->readinessTimeoutSeconds();

            $clientConfiguration            = $configuration->build($clientSettings, $timeoutSeconds);
            $clientConfiguration['retries'] = 0;

            return new SesV2Client($clientConfiguration);
        },
    ]);
};
