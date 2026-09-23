<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\CQRS;

use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\HandlerResolver;
use Backendbase\Shared\CQRS\Query;
use Override;
use UnexpectedValueException;

final readonly class RegistryHandlerResolver implements HandlerResolver
{
    /** @param array<string, string> $handlers */
    public function __construct(private array $handlers)
    {
    }

    /** @param Command|Query<mixed> $message */
    #[Override]
    public function handlerFor(Command|Query $message): string
    {
        $messageClass = $message::class;

        return $this->handlers[$messageClass]
            ?? throw new UnexpectedValueException('No CQRS handler registered for ' . $messageClass);
    }
}
