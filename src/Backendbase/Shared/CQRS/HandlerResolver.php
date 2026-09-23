<?php

declare(strict_types=1);

namespace Backendbase\Shared\CQRS;

interface HandlerResolver
{
    /** @param Command|Query<mixed> $message */
    public function handlerFor(Command|Query $message): string;
}
