<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\CQRS\Fixtures;

use Backendbase\Shared\CQRS\Query;
use Override;

/** @implements Query<string> */
final readonly class RegistryQuery implements Query
{
    #[Override]
    public function jsonSerialize(): string
    {
        return 'query';
    }
}
