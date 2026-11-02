<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Contacts;

final class ContactVerificationPolicy
{
    public const int MAXIMUM_FAILED_ATTEMPTS      = 5;
    public const int FAILED_ATTEMPT_RETRY_SECONDS = 3600;
    public const int RESEND_RETRY_SECONDS         = 60;
    public const string RESEND_COOLDOWN           = '+' . self::RESEND_RETRY_SECONDS . ' seconds';
    public const string CODE_LIFETIME             = '+5 minutes';
    public const string EVIDENCE_LIFETIME         = '+12 hours';
}
