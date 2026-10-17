<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\CQRS;

use Backendbase\Shared\CQRS\HandlerResolver;
use Backendbase\Shared\CQRS\Query;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\CQRS\QueryHandler;
use Override;
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

readonly class ContainerAwareQueryBus implements QueryBus
{
    public function __construct(
        private ContainerInterface $container,
        private HandlerResolver $handlerResolver,
    ) {
    }

    /**
     * @param Query<TResult> $query
     *
     * @return TResult
     *
     * @template TResult
     */
    #[Override]
    public function handle(Query $query): mixed
    {
        $handlerClass = $this->handlerResolver->handlerFor($query);

        $handler = $this->container->get($handlerClass);
        if (! $handler instanceof QueryHandler) {
            throw new UnexpectedValueException($handlerClass . ' is not a query handler.');
        }

        return $handler->handle($query);
    }
}
