<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives;

enum IsEnabled: int
{
    case ENABLED     = 1;
    case NOT_ENABLED = 0;
}
