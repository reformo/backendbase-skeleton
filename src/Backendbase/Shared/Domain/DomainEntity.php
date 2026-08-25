<?php

declare(strict_types=1);

namespace Backendbase\Shared\Domain;

use Backendbase\Shared\Domain\Identifier\EntityId;

interface DomainEntity
{
    public function id(): EntityId;

    /** @return array<string, mixed> */
    public function getState(): array;
}
