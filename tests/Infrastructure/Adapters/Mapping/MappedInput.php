<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Mapping;

final readonly class MappedInput
{
    public function __construct(public string $name, public int $age)
    {
    }
}
