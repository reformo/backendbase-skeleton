<?php

declare(strict_types=1);

namespace Tests\Shared\Services;

final readonly class MappedInput
{
    public function __construct(public string $name, public int $age)
    {
    }
}
