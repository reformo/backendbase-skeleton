<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration\Identity;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Registration\RegistrationPolicy;
use Backendbase\Shared\Settings;

use function array_key_exists;
use function is_array;

final readonly class RegistrationSettings
{
    private RegistrationPolicy $policy;

    public function __construct(Settings $settings)
    {
        $root     = $settings->get();
        $identity = is_array($root) && array_key_exists('identity', $root) ? $root['identity'] : [];
        if (! is_array($identity)) {
            throw AuthenticationUnavailable::create('The registration configuration is invalid.');
        }

        $values = array_key_exists('registration', $identity) ? $identity['registration'] : [];
        if (! is_array($values)) {
            throw AuthenticationUnavailable::create('The registration configuration is invalid.');
        }

        $this->policy = new RegistrationPolicy(
            self::boolean($values, 'require-mobile-verification', true),
            self::boolean($values, 'require-email-verification', false),
        );
    }

    public function policy(): RegistrationPolicy
    {
        return $this->policy;
    }

    /** @param array<array-key, mixed> $values */
    private static function boolean(array $values, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $values)) {
            return $default;
        }

        return match ($values[$key]) {
            true, 'true' => true,
            false, 'false' => false,
            default => throw AuthenticationUnavailable::create('The registration setting ' . $key . ' must be true or false.'),
        };
    }
}
