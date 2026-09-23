<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Composition;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

use function is_array;

final class MinistackCompositionTest extends TestCase
{
    #[Test]
    public function itConfiguresLocalAwsServicesInTheDefaultStack(): void
    {
        $configuration = self::arrayValue(Yaml::parseFile('docker-compose.yaml'));
        $services      = self::arrayValue($configuration['services'] ?? null);

        self::assertCount(6, $services);
        self::assertArrayNotHasKey('profiles', self::arrayValue($services['redis'] ?? null));
        self::assertArrayNotHasKey('profiles', self::arrayValue($services['mysql'] ?? null));
        self::assertArrayNotHasKey('profiles', self::arrayValue($services['rabbitmq'] ?? null));

        $ministack = self::arrayValue($services['ministack'] ?? null);
        self::assertArrayNotHasKey('profiles', $ministack);
        self::assertSame(
            'ministackorg/ministack:1.5.14@sha256:62c0ac878ea843596c481c2cf9a6d1f12d6e310209b50e0ab60fde3f38022c98',
            $ministack['image'],
        );
        self::assertSame(['127.0.0.1:${BACKENDBASE_DEV_AWS_PORT:-4566}:4566'], $ministack['ports']);
        self::assertSame('s3,sqs,sns,ses,cloudfront', self::arrayValue($ministack['environment'])['SERVICES']);
        self::assertSame('${AWS_ACCESS_KEY_ID:-test}', self::arrayValue($ministack['environment'])['AWS_ACCESS_KEY_ID']);
        self::assertSame('${AWS_SECRET_ACCESS_KEY:-test}', self::arrayValue($ministack['environment'])['AWS_SECRET_ACCESS_KEY']);
        self::assertArrayHasKey('healthcheck', $ministack);
        self::assertContains('ministack_state:/tmp/ministack-state', self::arrayValue($ministack['volumes']));
        self::assertContains('ministack_s3:/tmp/ministack-data/s3', self::arrayValue($ministack['volumes']));
        self::assertNotEmpty(self::arrayValue($ministack['post_start']));

        $cdn = self::arrayValue($services['cdn'] ?? null);
        self::assertSame(['127.0.0.1:${BACKENDBASE_DEV_CDN_PORT:-8081}:80'], $cdn['ports']);
        self::assertSame('${BUCKET_NAME:-backendbase-v3}', self::arrayValue($cdn['environment'])['BUCKET_NAME']);
        self::assertArrayHasKey('healthcheck', $cdn);

        $stackport = self::arrayValue($services['stackport'] ?? null);
        self::assertSame(
            'davireis/stackport:0.4.3@sha256:62931f2183b52f11281cacc4149569c769aa4e3f327452da9620fb773f20af68',
            $stackport['image'],
        );
        self::assertSame(['127.0.0.1:${BACKENDBASE_DEV_STACKPORT_PORT:-8082}:8080'], $stackport['ports']);
        self::assertSame('http://ministack:4566', self::arrayValue($stackport['environment'])['AWS_ENDPOINT_URL']);
        self::assertArrayHasKey('ministack', self::arrayValue($stackport['depends_on']));
        self::assertArrayHasKey('healthcheck', $stackport);
    }

    /** @return array<mixed> */
    private static function arrayValue(mixed $value): array
    {
        if (! is_array($value)) {
            self::fail('Expected an array value in docker-compose.yaml.');
        }

        return $value;
    }
}
