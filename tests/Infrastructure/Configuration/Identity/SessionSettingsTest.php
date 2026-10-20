<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Configuration\Identity;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Infrastructure\Configuration\Identity\SessionSettings;
use Backendbase\Shared\Services\Settings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SessionSettingsTest extends TestCase
{
    public function testDefaultPoliciesHaveSeparateTokenIdleAndMaximumDurations(): void
    {
        $settings = new SessionSettings(new Settings([]));
        $policies = $settings->policies();
        $user     = $policies->forRealm(Realm::USER);
        $admin    = $policies->forRealm(Realm::ADMIN);
        self::assertSame(['accessTokenLifetimeSeconds' => 900, 'idleTimeoutSeconds' => 86400, 'maximumLifetimeSeconds' => 604800], $user->toArray());
        self::assertSame(['accessTokenLifetimeSeconds' => 300, 'idleTimeoutSeconds' => 1800, 'maximumLifetimeSeconds' => 28800], $admin->toArray());
    }

    /** @param array<string, mixed> $configuration */
    #[DataProvider('invalidSettings')]
    public function testInvalidDurationsFailBeforeIssuingASession(array $configuration): void
    {
        $this->expectException(AuthenticationUnavailable::class);
        new SessionSettings(new Settings($configuration));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidSettings(): iterable
    {
        yield 'identity null' => [['identity' => null]];
        yield 'user null' => [['identity' => ['user' => null]]];
        yield 'admin scalar' => [['identity' => ['admin' => false]]];
        yield 'token too short' => [['identity' => ['user' => ['access-token-lifetime-seconds' => 59]]]];
        yield 'token too long' => [['identity' => ['admin' => ['access-token-lifetime-seconds' => 3601]]]];
        yield 'idle too short' => [['identity' => ['user' => ['session-idle-timeout-seconds' => 59]]]];
        yield 'maximum before idle' => [['identity' => ['admin' => ['session-maximum-lifetime-seconds' => 1799]]]];
        yield 'maximum beyond one year' => [['identity' => ['admin' => ['session-maximum-lifetime-seconds' => 31536001]]]];

        foreach (['user', 'admin'] as $realm) {
            yield from self::invalidTypes($realm);
        }
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    private static function invalidTypes(string $realm): iterable
    {
        foreach (['access-token-lifetime-seconds', 'session-idle-timeout-seconds', 'session-maximum-lifetime-seconds'] as $key) {
            yield $realm . ' null ' . $key => [['identity' => [$realm => [$key => null]]]];
            yield $realm . ' string ' . $key => [['identity' => [$realm => [$key => '900']]]];
            yield $realm . ' float ' . $key => [['identity' => [$realm => [$key => 900.0]]]];
            yield $realm . ' bool ' . $key => [['identity' => [$realm => [$key => true]]]];
        }
    }
}
