<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Contracts\Input;

use Backendbase\Domain\ExampleCatalog\Domain\Primitives\EntryDetails;
use Backendbase\Domain\ExampleCatalog\Domain\Primitives\EntryValue;

final readonly class EntryChanges
{
    public function __construct(
        public bool|null $isActive = null,
        public EntryValue|null $value = null,
        public EntryDetails|null $details = null,
    ) {
    }
}
