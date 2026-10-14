<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Challenges;

enum ChallengePurpose: string
{
    case OWNERSHIP       = 'ownership';
    case TOTP_ENROLLMENT = 'totp-enrollment';
    case LOGIN           = 'login';
}
