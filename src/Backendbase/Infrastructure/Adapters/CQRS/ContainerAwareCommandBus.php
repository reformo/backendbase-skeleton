<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\CQRS;

use Backendbase\Shared\CQRS\Attributes\CQRSHandler;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\CQRS\CommandHandler;
use Override;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use UnexpectedValueException;

readonly class ContainerAwareCommandBus implements CommandBus
{
    public function __construct(private ContainerInterface $container)
    {
    }

    #[Override]
    public function handle(Command $command): void
    {
        $commandFQCN = $command::class;
        $reflection  = new ReflectionClass($commandFQCN);
        $handlerFQCN = $reflection->getAttributes(CQRSHandler::class)[0]->getArguments()[0];

        $handler = $this->container->get($handlerFQCN);
        if (! $handler instanceof CommandHandler) {
            throw new UnexpectedValueException($handlerFQCN . ' is not a command handler.');
        }

        $handler->handle($command);
    }
}
