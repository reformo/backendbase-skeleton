<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Configuration\Identity;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Registration\RegistrationPolicy;
use Backendbase\Infrastructure\Configuration\Identity\RegistrationSettings;
use Backendbase\Shared\Services\Settings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Infrastructure\Composition\ProductionContainerFixture;

final class RegistrationSettingsTest extends TestCase
{
    public function testMissingConfigurationRetainsMobileOnlyRequirementThroughProductionBinding(): void
    {
        $container = ProductionContainerFixture::build([]);
        $policy    = $container->get(RegistrationPolicy::class);
        self::assertTrue($policy->requires('mobile'));
        self::assertFalse($policy->requires('email'));
    }

    #[DataProvider('combinations')]
    public function testEnvironmentSettingsResolveAllFourPolicies(string $mobile, string $email): void
    {
        $original = $_ENV;
        try {
            $_ENV['BACKENDBASE_IDENTITY_REGISTRATION_REQUIRE_MOBILE_VERIFICATION'] = $mobile;
            $_ENV['BACKENDBASE_IDENTITY_REGISTRATION_REQUIRE_EMAIL_VERIFICATION']  = $email;
            $configuration                                                         = require 'config/autoload/identity-global.php';
            $settings                                                              = new RegistrationSettings(new Settings($configuration));
            $container                                                             = ProductionContainerFixture::build([RegistrationSettings::class => $settings]);
            $policy                                                                = $container->get(RegistrationPolicy::class);
            self::assertSame($mobile === 'true', $policy->requires('mobile'));
            self::assertSame($email === 'true', $policy->requires('email'));
            self::assertSame($mobile === 'true' || $email === 'true', $policy->requiresContext());
        } finally {
            $_ENV = $original;
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function combinations(): iterable
    {
        yield 'mobile' => ['true', 'false'];
        yield 'email' => ['false', 'true'];
        yield 'both' => ['true', 'true'];
        yield 'neither' => ['false', 'false'];
    }

    /** @param array<string, mixed> $configuration */
    #[DataProvider('invalidSettings')]
    public function testInvalidConfigurationNeverDisablesVerification(array $configuration): void
    {
        $this->expectException(AuthenticationUnavailable::class);
        new RegistrationSettings(new Settings($configuration));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidSettings(): iterable
    {
        yield 'identity null' => [['identity' => null]];
        yield 'registration null' => [['identity' => ['registration' => null]]];
        yield 'registration scalar' => [['identity' => ['registration' => 'false']]];

        foreach (['', 'FALSE', '0', '1', 'yes', ' true ', 0, 1, null, []] as $index => $invalid) {
            yield 'mobile ' . $index => [['identity' => ['registration' => ['require-mobile-verification' => $invalid]]]];
            yield 'email ' . $index => [['identity' => ['registration' => ['require-email-verification' => $invalid]]]];
        }
    }
}
