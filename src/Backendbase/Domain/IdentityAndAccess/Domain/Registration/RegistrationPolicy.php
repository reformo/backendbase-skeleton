<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Registration;

final readonly class RegistrationPolicy
{
    public function __construct(private bool $requireMobileVerification, private bool $requireEmailVerification)
    {
    }

    public function requires(string $contactType): bool
    {
        if ($contactType === 'mobile') {
            return $this->requireMobileVerification;
        }

        return $this->requireEmailVerification;
    }

    public function requiresContext(): bool
    {
        $mobileRequired = $this->requireMobileVerification;

        return $mobileRequired || $this->requireEmailVerification;
    }
}
