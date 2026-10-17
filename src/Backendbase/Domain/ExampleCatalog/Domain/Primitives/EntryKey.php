<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Domain\Primitives;

use Backendbase\Shared\Exception\InvalidUserInput;

use function mb_check_encoding;
use function mb_strlen;

final readonly class EntryKey
{
    public function __construct(private string $value)
    {
        if (! mb_check_encoding($value, 'UTF-8') || $value === '' || mb_strlen($value, 'UTF-8') > 160) {
            throw InvalidUserInput::create('The lookupKey value must contain 1 to 160 UTF-8 characters.');
        }
    }

    public function toString(): string
    {
        return $this->value;
    }
}
