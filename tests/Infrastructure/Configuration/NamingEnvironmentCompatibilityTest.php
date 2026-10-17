<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Configuration;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Infrastructure\Configuration\IdentitySettings;
use Backendbase\Shared\Services\Settings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function base64_encode;
use function str_repeat;

final class NamingEnvironmentCompatibilityTest extends TestCase
{
    #[DataProvider('keySources')]
    public function testProofKeysKeepCanonicalPrecedenceAndLegacyFallback(bool $canonical, string $expectedId, string $expectedByte): void
    {
        $originalEnvironment = $_ENV;
        try {
            $_ENV['BACKENDBASE_IDENTITY_ENCRYPTION_KEY_ID']             = 'legacy';
            $_ENV['BACKENDBASE_IDENTITY_ENCRYPTION_KEY']                = base64_encode(str_repeat('l', 32));
            $_ENV['BACKENDBASE_IDENTITY_PREVIOUS_CONTACT_PROOF_KEY_ID'] = 'previous';
            $_ENV['BACKENDBASE_IDENTITY_PREVIOUS_CONTACT_PROOF_KEY']    = base64_encode(str_repeat('p', 32));
            if ($canonical) {
                $_ENV['BACKENDBASE_IDENTITY_CONTACT_PROOF_KEY_ID'] = 'canonical';
                $_ENV['BACKENDBASE_IDENTITY_CONTACT_PROOF_KEY']    = base64_encode(str_repeat('c', 32));
            }

            $configuration = require 'config/autoload/identity.global.php';
            $settings      = new IdentitySettings(new Settings($configuration));
            self::assertSame($expectedId, $settings->contactProofKeyId());
            self::assertSame([$expectedId => str_repeat($expectedByte, 32), 'previous' => str_repeat('p', 32)], $settings->contactProofKeys());
        } finally {
            $_ENV = $originalEnvironment;
        }
    }

    /** @return iterable<string, array{bool, string, string}> */
    public static function keySources(): iterable
    {
        yield 'canonical overrides legacy' => [true, 'canonical', 'c'];
        yield 'legacy remains readable' => [false, 'legacy', 'l'];
    }

    public function testEmptyCanonicalProofKeyIsRejectedWithoutUsingTheLegacyValue(): void
    {
        $originalEnvironment = $_ENV;
        try {
            $_ENV['BACKENDBASE_IDENTITY_CONTACT_PROOF_KEY_ID'] = 'canonical';
            $_ENV['BACKENDBASE_IDENTITY_CONTACT_PROOF_KEY']    = '';
            $_ENV['BACKENDBASE_IDENTITY_ENCRYPTION_KEY']       = base64_encode(str_repeat('l', 32));
            $configuration                                     = require 'config/autoload/identity.global.php';
            $settings                                          = new IdentitySettings(new Settings($configuration));
            $this->expectException(AuthenticationUnavailable::class);
            $settings->contactProofKeys();
        } finally {
            $_ENV = $originalEnvironment;
        }
    }

    public function testStdoutSettingPreservesPresenceSemantics(): void
    {
        $originalEnvironment = $_ENV;
        try {
            $_ENV['BACKENDBASE_LOG_TO_STDOUT'] = '';
            $_ENV['BACKENDBASE_LOGGER_LEVEL']  = 'info';
            $configuration                     = require 'config/autoload/logger.global.php';
            self::assertSame('php://stdout', $configuration['logger']['path']);
        } finally {
            $_ENV = $originalEnvironment;
        }
    }
}
