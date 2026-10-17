<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts;

interface OperatorStore
{
    /**
     * @param callable(): Result $work
     *
     * @return Result
     *
     * @template Result
     */
    public function exclusive(callable $work): mixed;

    public function assertFirstAdminAvailable(): void;

    public function assertBootstrapActor(string $actorId): void;
}
