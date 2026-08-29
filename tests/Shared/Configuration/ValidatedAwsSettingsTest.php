<?php

declare(strict_types=1);

namespace Tests\Shared\Configuration;

use Backendbase\Shared\Configuration\ValidatedAwsSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class ValidatedAwsSettingsTest extends TestCase
{
    #[Test]
    public function itReturnsValidatedAwsShapes(): void
    {
        $client      = [
            'credentials' => ['key' => 'access-key', 'secret' => 'secret-key'],
            'region' => 'eu-central-1',
            'endpoint' => 'https://aws.example.com',
        ];
        $sqs         = [
            'continuous' => false,
            'maxNumberOfMessages' => 10,
            'queue' => 'events',
            'queueUrl' => '',
            'visibilityTimeout' => 30,
            'waitTimeSeconds' => 20,
        ];
        $sns         = ['smsType' => 'Transactional', 'senderId' => 'Backendbase'];
        $objectStore = [
            'credentials' => ['key' => 'access-key', 'secret' => 'secret-key'],
            'region' => 'eu-central-1',
            'endpoint' => 'https://s3.example.com',
            'bucket' => 'assets',
            'cdnBaseUrl' => null,
        ];

        self::assertSame($client, ValidatedAwsSettings::client($client));
        self::assertSame($sqs, ValidatedAwsSettings::sqs($sqs));
        self::assertSame($sns, ValidatedAwsSettings::sns($sns));
        self::assertSame($objectStore, ValidatedAwsSettings::objectStore($objectStore));
    }

    /** @param callable(mixed): array<string, mixed> $validator */
    #[DataProvider('invalidSettings')]
    #[Test]
    public function itRejectsInvalidAwsShapes(callable $validator, mixed $settings, string $message): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        $validator($settings);
    }

    /** @return iterable<string, array{callable(mixed): array<string, mixed>, mixed, string}> */
    public static function invalidSettings(): iterable
    {
        yield 'missing region' => [
            ValidatedAwsSettings::client(...),
            ['credentials' => ['key' => '', 'secret' => '']],
            'The AWS region is missing.',
        ];

        yield 'invalid credentials' => [
            ValidatedAwsSettings::client(...),
            ['region' => 'eu-central-1', 'credentials' => 'invalid'],
            'The AWS credentials configuration is invalid.',
        ];

        yield 'invalid credential value' => [
            ValidatedAwsSettings::client(...),
            ['region' => 'eu-central-1', 'credentials' => ['key' => 1, 'secret' => 'secret']],
            'The AWS credentials must be strings.',
        ];

        yield 'incomplete credentials' => [
            ValidatedAwsSettings::client(...),
            ['region' => 'eu-central-1', 'credentials' => ['key' => 'key', 'secret' => '']],
            'The AWS access key and secret key must be configured together.',
        ];

        yield 'invalid endpoint' => [
            ValidatedAwsSettings::client(...),
            ['region' => 'eu-central-1', 'credentials' => ['key' => '', 'secret' => ''], 'endpoint' => 1],
            'The AWS endpoint must be a string.',
        ];

        yield 'SQS' => [ValidatedAwsSettings::sqs(...), ['queue' => 1], 'The AWS SQS settings are invalid.'];
        yield 'SNS' => [ValidatedAwsSettings::sns(...), ['senderId' => 1], 'The AWS SNS settings are invalid.'];
        yield 'object store' => [
            ValidatedAwsSettings::objectStore(...),
            ['credentials' => []],
            'The object-store settings are invalid.',
        ];

        yield 'object store endpoint' => [
            ValidatedAwsSettings::objectStore(...),
            [
                'credentials' => ['key' => '', 'secret' => ''],
                'region' => 'eu-central-1',
                'endpoint' => 1,
                'bucket' => 'assets',
            ],
            'The object-store settings are invalid.',
        ];
    }
}
