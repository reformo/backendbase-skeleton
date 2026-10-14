<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

use Backendbase\Domain\IdentityAndAccess\Contracts\Input\OperatorInput;
use DateTimeImmutable;

interface OperatorStore
{
    public function assertFirstAdminAvailable(): void;

    public function assertBootstrapActor(string $actorId): void;

    public function record(OperatorInput $input, string $targetId, DateTimeImmutable $now): void;
}
