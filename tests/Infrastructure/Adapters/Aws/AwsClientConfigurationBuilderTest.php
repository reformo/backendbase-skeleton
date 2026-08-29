<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Aws;

use Aws\Credentials\Credentials;
use Backendbase\Infrastructure\Adapters\Aws\AwsClientConfigurationBuilder;
use Backendbase\Infrastructure\Configuration\Aws\AwsClientSettings;
use Backendbase\Infrastructure\Configuration\Aws\AwsCredentials;
use Backendbase\Infrastructure\Configuration\Aws\AwsLocationSettings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AwsClientConfigurationBuilderTest extends TestCase
{
    #[Test]
    public function itBuildsACompleteClientConfiguration(): void
    {
        $settings      = new AwsClientSettings(
            new AwsCredentials('access-key', 'secret-key'),
            new AwsLocationSettings('eu-central-1', 'https://aws.example.com'),
        );
        $configuration = new AwsClientConfigurationBuilder()->build($settings, 2.5);

        self::assertSame('eu-central-1', $configuration['region']);
        self::assertInstanceOf(Credentials::class, $configuration['credentials']);
        self::assertSame('https://aws.example.com', $configuration['endpoint']);
        self::assertSame(['connect_timeout' => 2.5, 'timeout' => 2.5], $configuration['http']);
    }

    #[Test]
    public function itOmitsOptionalClientConfiguration(): void
    {
        $settings      = new AwsClientSettings(
            new AwsCredentials('', ''),
            new AwsLocationSettings('eu-central-1', ''),
        );
        $configuration = new AwsClientConfigurationBuilder()->build($settings, 2.0);

        self::assertArrayNotHasKey('credentials', $configuration);
        self::assertArrayNotHasKey('endpoint', $configuration);
    }
}
