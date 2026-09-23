<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\CQRS;

use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\CQRS\CommandHandler;
use Backendbase\Shared\CQRS\HandlerResolver;
use Override;
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

readonly class ContainerAwareCommandBus implements CommandBus
{
    public function __construct(
        private ContainerInterface $container,
        private HandlerResolver $handlerResolver,
    ) {
    }

    #[Override]
    public function handle(Command $command): void
    {
        $handlerFQCN = $this->handlerResolver->handlerFor($command);

        $handler = $this->container->get($handlerFQCN);
        if (! $handler instanceof CommandHandler) {
            throw new UnexpectedValueException($handlerFQCN . ' is not a command handler.');
        }

        $handler->handle($command);
    }
}
