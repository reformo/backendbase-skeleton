<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\CQRS;

use Backendbase\Shared\CQRS\Attributes\CQRSHandler;
use Backendbase\Shared\CQRS\Query;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\CQRS\QueryHandler;
use Override;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use UnexpectedValueException;

readonly class ContainerAwareQueryBus implements QueryBus
{
    public function __construct(private ContainerInterface $container)
    {
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
        $queryFQCN   = $query::class;
        $reflection  = new ReflectionClass($queryFQCN);
        $handlerFQCN = $reflection->getAttributes(CQRSHandler::class)[0]->getArguments()[0];

        $handler = $this->container->get($handlerFQCN);
        if (! $handler instanceof QueryHandler) {
            throw new UnexpectedValueException($handlerFQCN . ' is not a query handler.');
        }

        return $handler->handle($query);
    }
}
