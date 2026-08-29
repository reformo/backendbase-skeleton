<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Composition;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

use function is_array;

final class AwsEmulatorProfileTest extends TestCase
{
    #[Test]
    public function itKeepsTheAwsEmulatorOutOfTheDefaultStack(): void
    {
        $configuration = self::arrayValue(Yaml::parseFile('docker-compose.yaml'));
        $services      = self::arrayValue($configuration['services'] ?? null);

        self::assertCount(4, $services);
        self::assertArrayNotHasKey('profiles', self::arrayValue($services['redis'] ?? null));
        self::assertArrayNotHasKey('profiles', self::arrayValue($services['mysql'] ?? null));
        self::assertArrayNotHasKey('profiles', self::arrayValue($services['rabbitmq'] ?? null));

        $emulator = self::arrayValue($services['aws-emulator'] ?? null);
        self::assertSame(['integration'], $emulator['profiles']);
        self::assertSame(
            'motoserver/moto:5.2.3@sha256:91fd602a21f49cf9eb82fdf474015a3c131d40104c8297ea6a2ca920708ae32c',
            $emulator['image'],
        );
        self::assertSame(['127.0.0.1:${BACKENDBASE_DEV_AWS_PORT:-5000}:5000'], $emulator['ports']);
        self::assertArrayHasKey('healthcheck', $emulator);
        self::assertArrayNotHasKey('volumes', $emulator);
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
