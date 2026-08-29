<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Aws;

use Aws\Credentials\Credentials;
use Backendbase\Infrastructure\Adapters\Aws\AwsClientConfigurationBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class AwsClientConfigurationBuilderTest extends TestCase
{
    #[Test]
    public function itBuildsACompleteClientConfiguration(): void
    {
        $configuration = new AwsClientConfigurationBuilder()->build([
            'region' => 'eu-central-1',
            'credentials' => ['key' => 'access-key', 'secret' => 'secret-key'],
            'endpoint' => 'https://aws.example.com',
        ], 2.5);

        self::assertSame('eu-central-1', $configuration['region']);
        self::assertInstanceOf(Credentials::class, $configuration['credentials']);
        self::assertSame('https://aws.example.com', $configuration['endpoint']);
        self::assertSame(['connect_timeout' => 2.5, 'timeout' => 2.5], $configuration['http']);
    }

    /** @param array<string, mixed> $settings */
    #[DataProvider('invalidSettings')]
    #[Test]
    public function itRejectsInvalidClientConfiguration(array $settings, string $message): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        new AwsClientConfigurationBuilder()->build($settings, 2.0);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidSettings(): iterable
    {
        yield 'missing region' => [
            ['credentials' => ['key' => '', 'secret' => '']],
            'The AWS region is missing.',
        ];

        yield 'invalid credentials collection' => [
            ['region' => 'eu-central-1', 'credentials' => 'invalid'],
            'The AWS credentials configuration is invalid.',
        ];

        yield 'non-string credential' => [
            ['region' => 'eu-central-1', 'credentials' => ['key' => 1, 'secret' => 'secret']],
            'The AWS credentials must be strings.',
        ];

        yield 'incomplete credential pair' => [
            ['region' => 'eu-central-1', 'credentials' => ['key' => 'access-key', 'secret' => '']],
            'The AWS access key and secret key must be configured together.',
        ];
    }
}
