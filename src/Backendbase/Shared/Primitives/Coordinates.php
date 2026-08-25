<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives;

readonly class Coordinates
{
    public function __construct(private string $longitude, private string $latitude)
    {
    }

    public function latitude(): string
    {
        return $this->latitude;
    }

    public function longitude(): string
    {
        return $this->longitude;
    }
}
