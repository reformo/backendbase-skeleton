<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Configuration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_key_exists;
use function dirname;

final class ObjectStoreConfigurationTest extends TestCase
{
    #[Test]
    public function itUsesTheAwsEndpointWhenTheObjectStoreEndpointIsEmpty(): void
    {
        $objectStoreEndpointExists = array_key_exists('OBJECT_STORE_ENDPOINT', $_ENV);
        $awsEndpointExists         = array_key_exists('AWS_ENDPOINT', $_ENV);
        $objectStoreEndpoint       = $_ENV['OBJECT_STORE_ENDPOINT'] ?? null;
        $awsEndpoint               = $_ENV['AWS_ENDPOINT'] ?? null;

        try {
            $_ENV['OBJECT_STORE_ENDPOINT'] = '';
            $_ENV['AWS_ENDPOINT']          = 'https://s3.example.com';

            $config = require dirname(__DIR__, 3) . '/config/autoload/object-store.global.php';

            self::assertSame('https://s3.example.com', $config['objectStore']['endpoint']);
        } finally {
            $this->restoreEnvironmentValue('OBJECT_STORE_ENDPOINT', $objectStoreEndpointExists, $objectStoreEndpoint);
            $this->restoreEnvironmentValue('AWS_ENDPOINT', $awsEndpointExists, $awsEndpoint);
        }
    }

    private function restoreEnvironmentValue(string $key, bool $exists, mixed $value): void
    {
        if ($exists) {
            $_ENV[$key] = $value;

            return;
        }

        unset($_ENV[$key]);
    }
}
