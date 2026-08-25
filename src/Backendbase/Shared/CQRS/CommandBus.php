<?php

declare(strict_types=1);

namespace Backendbase\Shared\CQRS;

interface CommandBus
{
    public function handle(Command $command): void;
}
