<?php

declare(strict_types=1);

namespace Backendbase\Shared\Domain\Identifier;

use Stringable;

interface EntityObjectId extends Stringable
{
    public static function generate(): self;

    public function id(): string;
}
