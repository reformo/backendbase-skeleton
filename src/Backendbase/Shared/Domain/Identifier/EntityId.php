<?php

declare(strict_types=1);

namespace Backendbase\Shared\Domain\Identifier;

interface EntityId
{
    public static function fromString(string $uuid): EntityId;

    public function toString(): string;

    public static function create(): EntityId;
}
