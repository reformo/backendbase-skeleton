<?php

declare(strict_types=1);

namespace Backendbase\Shared\Configuration;

use UnexpectedValueException;

use function array_key_exists;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;

/**
 * @phpstan-type AwsClientSettings array{
 *     credentials: array{key: string, secret: string},
 *     region: non-empty-string,
 *     endpoint: string
 * }
 * @phpstan-type SqsSettings array{
 *     continuous?: bool,
 *     maxNumberOfMessages?: int,
 *     queue?: string,
 *     queueUrl?: string,
 *     visibilityTimeout?: int,
 *     waitTimeSeconds?: int
 * }
 * @phpstan-type SnsSettings array{smsType?: string, senderId?: string}
 * @phpstan-type ObjectStoreSettings array{
 *     credentials: array{key: string, secret: string},
 *     region: string,
 *     endpoint: string,
 *     bucket: string,
 *     cdnBaseUrl: string|null
 * }
 */
final class ValidatedAwsSettings
{
    /** @return AwsClientSettings */
    public static function client(mixed $settings): array
    {
        $credentials = is_array($settings) ? ($settings['credentials'] ?? null) : null;
        $region      = is_array($settings) ? ($settings['region'] ?? null) : null;
        if (! is_string($region) || $region === '') {
            throw new UnexpectedValueException('The AWS region is missing.');
        }

        if (! is_array($credentials)) {
            throw new UnexpectedValueException('The AWS credentials configuration is invalid.');
        }

        $accessKey = $credentials['key'] ?? null;
        $secretKey = $credentials['secret'] ?? null;
        if (! is_string($accessKey) || ! is_string($secretKey)) {
            throw new UnexpectedValueException('The AWS credentials must be strings.');
        }

        if (($accessKey === '') !== ($secretKey === '')) {
            throw new UnexpectedValueException('The AWS access key and secret key must be configured together.');
        }

        $endpoint = $settings['endpoint'] ?? '';
        if (! is_string($endpoint)) {
            throw new UnexpectedValueException('The AWS endpoint must be a string.');
        }

        return [
            'credentials' => ['key' => $accessKey, 'secret' => $secretKey],
            'region' => $region,
            'endpoint' => $endpoint,
        ];
    }

    /** @return SqsSettings */
    public static function sqs(mixed $settings): array
    {
        if (
            ! is_array($settings)
            || (array_key_exists('continuous', $settings) && ! is_bool($settings['continuous']))
            || (array_key_exists('maxNumberOfMessages', $settings) && ! is_int($settings['maxNumberOfMessages']))
            || (array_key_exists('queue', $settings) && ! is_string($settings['queue']))
            || (array_key_exists('queueUrl', $settings) && ! is_string($settings['queueUrl']))
            || (array_key_exists('visibilityTimeout', $settings) && ! is_int($settings['visibilityTimeout']))
            || (array_key_exists('waitTimeSeconds', $settings) && ! is_int($settings['waitTimeSeconds']))
        ) {
            throw new UnexpectedValueException('The AWS SQS settings are invalid.');
        }

        return [
            ...(array_key_exists('continuous', $settings) ? ['continuous' => $settings['continuous']] : []),
            ...(array_key_exists('maxNumberOfMessages', $settings)
                ? ['maxNumberOfMessages' => $settings['maxNumberOfMessages']]
                : []),
            ...(array_key_exists('queue', $settings) ? ['queue' => $settings['queue']] : []),
            ...(array_key_exists('queueUrl', $settings) ? ['queueUrl' => $settings['queueUrl']] : []),
            ...(array_key_exists('visibilityTimeout', $settings)
                ? ['visibilityTimeout' => $settings['visibilityTimeout']]
                : []),
            ...(array_key_exists('waitTimeSeconds', $settings)
                ? ['waitTimeSeconds' => $settings['waitTimeSeconds']]
                : []),
        ];
    }

    /** @return SnsSettings */
    public static function sns(mixed $settings): array
    {
        if (
            ! is_array($settings)
            || (array_key_exists('smsType', $settings) && ! is_string($settings['smsType']))
            || (array_key_exists('senderId', $settings) && ! is_string($settings['senderId']))
        ) {
            throw new UnexpectedValueException('The AWS SNS settings are invalid.');
        }

        return [
            ...(array_key_exists('smsType', $settings) ? ['smsType' => $settings['smsType']] : []),
            ...(array_key_exists('senderId', $settings) ? ['senderId' => $settings['senderId']] : []),
        ];
    }

    /** @return ObjectStoreSettings */
    public static function objectStore(mixed $settings): array
    {
        $credentials = is_array($settings) ? ($settings['credentials'] ?? null) : null;
        if (
            ! is_array($settings)
            || ! is_array($credentials)
            || ! is_string($credentials['key'] ?? null)
            || ! is_string($credentials['secret'] ?? null)
            || ! is_string($settings['region'] ?? null)
            || ! is_string($settings['endpoint'] ?? null)
            || ! is_string($settings['bucket'] ?? null)
            || (! is_string($settings['cdnBaseUrl'] ?? null) && ($settings['cdnBaseUrl'] ?? null) !== null)
        ) {
            throw new UnexpectedValueException('The object-store settings are invalid.');
        }

        return [
            'credentials' => ['key' => $credentials['key'], 'secret' => $credentials['secret']],
            'region' => $settings['region'],
            'endpoint' => $settings['endpoint'],
            'bucket' => $settings['bucket'],
            'cdnBaseUrl' => $settings['cdnBaseUrl'] ?? null,
        ];
    }
}
