<?php

declare(strict_types=1);

namespace Backendbase\Shared\Persistence;

enum ExternalEffectResolution: string
{
    case ACCEPTED     = 'accepted';
    case NOT_ACCEPTED = 'not-accepted';
}
