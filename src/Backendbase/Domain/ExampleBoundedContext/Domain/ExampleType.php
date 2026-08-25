<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Domain;

enum ExampleType: string
{
    case SYSTEM = 'system';
    case USER   = 'user';
}
