<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Domain\Primitives;

use Backendbase\Shared\Exception\InvalidUserInput;

use function mb_check_encoding;
use function mb_strlen;

final readonly class EntryValue
{
    public function __construct(private string $value)
    {
        if (! mb_check_encoding($value, 'UTF-8') || mb_strlen($value, 'UTF-8') > 2048) {
            throw InvalidUserInput::create('The lookupValue value must contain 0 to 2048 UTF-8 characters.');
        }
    }

    public function toString(): string
    {
        return $this->value;
    }
}
