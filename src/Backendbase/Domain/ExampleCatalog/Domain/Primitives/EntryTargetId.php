<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Domain\Primitives;

use Backendbase\Shared\Exception\InvalidUserInput;

final readonly class EntryTargetId
{
    public function __construct(private int|null $value)
    {
        if ($value !== null && $value < 1) {
            throw InvalidUserInput::create('The typeTargetId value must be a positive integer or null.');
        }
    }

    public function toInteger(): int|null
    {
        return $this->value;
    }
}
