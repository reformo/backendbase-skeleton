<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration\Identity;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RealmSessionPolicies;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\SessionPolicy;
use Backendbase\Shared\Settings;
use InvalidArgumentException;

use function is_array;
use function is_int;

final readonly class SessionSettings
{
    private RealmSessionPolicies $policies;

    public function __construct(Settings $settings)
    {
        $root     = $settings->get();
        $root     = is_array($root) ? $root + ['identity' => []] : [];
        $identity = $root['identity'] ?? null;
        if (! is_array($identity)) {
            throw AuthenticationUnavailable::create('The session configuration is invalid.');
        }

        $identity      += ['user' => [], 'admin' => []];
        $this->policies = new RealmSessionPolicies(self::policy($identity['user'], 900, 86400, 604800), self::policy($identity['admin'], 300, 1800, 28800));
    }

    public function policies(): RealmSessionPolicies
    {
        return $this->policies;
    }

    private static function policy(mixed $values, int $tokenDefault, int $idleDefault, int $maximumDefault): SessionPolicy
    {
        if (! is_array($values)) {
            throw AuthenticationUnavailable::create('The session configuration is invalid.');
        }

        $values += ['access-token-lifetime-seconds' => $tokenDefault, 'session-idle-timeout-seconds' => $idleDefault, 'session-maximum-lifetime-seconds' => $maximumDefault];
        $token   = $values['access-token-lifetime-seconds'];
        $idle    = $values['session-idle-timeout-seconds'];
        $maximum = $values['session-maximum-lifetime-seconds'];
        if (! is_int($token) || ! is_int($idle) || ! is_int($maximum)) {
            throw AuthenticationUnavailable::create('Session durations must be integers.');
        }

        try {
            return new SessionPolicy($token, $idle, $maximum);
        } catch (InvalidArgumentException $exception) {
            throw AuthenticationUnavailable::create('The session duration configuration is invalid.', previous: $exception);
        }
    }
}
