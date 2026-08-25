<?php

declare(strict_types=1);

namespace Backendbase\Shared\CQRS;

interface CommandHandler
{
    public function handle(Command $command): void;
}
