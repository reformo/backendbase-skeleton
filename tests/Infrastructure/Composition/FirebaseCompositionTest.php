<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Composition;

use GuzzleHttp\ClientInterface;
use Kreait\Firebase\Contract\Messaging;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function getenv;
use function json_encode;
use function openssl_pkey_export;
use function openssl_pkey_new;
use function putenv;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const JSON_THROW_ON_ERROR;

final class FirebaseCompositionTest extends TestCase
{
    #[DataProvider('credentials')]
    public function testProductionFactoryLoadsOnlyTheTemporaryCredentials(bool $explicit): void
    {
        $path = tempnam(sys_get_temp_dir(), 'backendbase-firebase-');
        self::assertIsString($path);
        $previous            = getenv('GOOGLE_APPLICATION_CREDENTIALS');
        $previousEnvironment = $_ENV;
        try {
            $key = openssl_pkey_new(['private_key_bits' => 2048]);
            self::assertNotFalse($key);
            self::assertTrue(openssl_pkey_export($key, $privateKey));
            file_put_contents($path, json_encode([
                'type' => 'service_account',
                'project_id' => 'fixture',
                'private_key_id' => 'fixture',
                'private_key' => $privateKey,
                'client_email' => 'fixture@fixture.iam.gserviceaccount.com',
                'client_id' => '123456789',
                'token_uri' => 'https://oauth2.googleapis.com/token',
            ], JSON_THROW_ON_ERROR));
            putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $path);
            $_ENV['GOOGLE_APPLICATION_CREDENTIALS'] = $path;
            $container                              = ProductionContainerFixture::configured([
                'notification' => ['push' => ['project-id' => 'fixture', 'credentials-path' => $explicit ? $path : '', 'timeout-seconds' => 1.5]],
            ]);
            self::assertInstanceOf(Messaging::class, $container->get(Messaging::class));
            self::assertInstanceOf(ClientInterface::class, $container->get(ClientInterface::class));
        } finally {
            putenv($previous === false ? 'GOOGLE_APPLICATION_CREDENTIALS' : 'GOOGLE_APPLICATION_CREDENTIALS=' . $previous);
            $_ENV = $previousEnvironment;
            unlink($path);
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function credentials(): iterable
    {
        yield 'explicit service account' => [true];
        yield 'application default service account' => [false];
    }
}
