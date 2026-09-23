<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Domain;

enum EntryType: string
{
    case SYSTEM = 'system';
    case USER   = 'user';
}
