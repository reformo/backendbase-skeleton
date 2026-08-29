<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Configuration;

use Backendbase\Infrastructure\Configuration\Aws\AwsCredentials;
use Backendbase\Infrastructure\Configuration\Aws\AwsLocationSettings;
use Backendbase\Infrastructure\Configuration\AwsSettings;
use Backendbase\Shared\Services\Settings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class AwsSettingsTest extends TestCase
{
    #[Test]
    public function itProvidesValidatedAwsValues(): void
    {
        $settings = new AwsSettings(new Settings([
            'aws' => [
                'credentials' => ['key' => 'access-key', 'secret' => 'secret-key'],
                'region' => 'eu-central-1',
                'endpoint' => '',
                'sqs' => [
                    'continuous' => false,
                    'maxNumberOfMessages' => 10,
                    'queue' => 'events',
                    'queueUrl' => 'https://example.com/events',
                    'visibilityTimeout' => 30,
                    'waitTimeSeconds' => 20,
                ],
                'sns' => ['smsType' => 'Transactional', 'senderId' => 'Backendbase'],
            ],
            'objectStore' => [
                'credentials' => ['key' => 'access-key', 'secret' => 'secret-key'],
                'region' => 'eu-central-1',
                'endpoint' => 'https://s3.example.com',
                'bucket' => 'assets',
                'cdnBaseUrl' => null,
            ],
            'readiness' => ['timeoutSeconds' => 2],
        ]));

        self::assertSame('eu-central-1', $settings->client()->region());
        self::assertSame('', $settings->client()->endpoint());
        self::assertSame('access-key', $settings->client()->credentials()->accessKey());
        self::assertSame('secret-key', $settings->client()->credentials()->secretKey());
        self::assertFalse($settings->sqs()->continuous());
        self::assertSame(10, $settings->sqs()->maxNumberOfMessages());
        self::assertSame('events', $settings->sqs()->queueName());
        self::assertSame('https://example.com/events', $settings->sqs()->queueUrl());
        self::assertSame(30, $settings->sqs()->visibilityTimeoutSeconds());
        self::assertSame(20, $settings->sqs()->waitTimeSeconds());
        self::assertSame('Transactional', $settings->sns()->smsType());
        self::assertSame('Backendbase', $settings->sns()->senderId());
        self::assertSame('access-key', $settings->objectStore()->credentials()->accessKey());
        self::assertSame('eu-central-1', $settings->objectStore()->region());
        self::assertSame('https://s3.example.com', $settings->objectStore()->endpoint());
        self::assertSame('assets', $settings->objectStore()->bucket());
        self::assertNull($settings->objectStore()->cdnBaseUrl());
        self::assertSame(2.0, $settings->readinessTimeoutSeconds());
    }

    #[Test]
    public function itRejectsIncompleteCredentials(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The AWS access key and secret key must be configured together.');

        new AwsCredentials('access-key', '');
    }

    #[Test]
    public function itRejectsAnEmptyAwsRegion(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The AWS region is missing.');

        new AwsLocationSettings('', '');
    }

    #[Test]
    public function itRejectsAnInvalidSmsTypeDuringConfiguration(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The SNS SMS type is invalid.');

        new AwsSettings(new Settings([
            'aws' => [
                'credentials' => ['key' => '', 'secret' => ''],
                'region' => 'eu-central-1',
                'endpoint' => '',
                'sqs' => [],
                'sns' => ['smsType' => 'invalid'],
            ],
            'objectStore' => [
                'credentials' => ['key' => '', 'secret' => ''],
                'region' => '',
                'endpoint' => '',
                'bucket' => '',
                'cdnBaseUrl' => null,
            ],
            'readiness' => ['timeoutSeconds' => 2],
        ]));
    }
}
