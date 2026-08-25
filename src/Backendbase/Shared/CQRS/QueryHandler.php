<?php

declare(strict_types=1);

namespace Backendbase\Shared\CQRS;

/**
 * @template TQuery of Query<mixed>
 * @template TResult
 */
interface QueryHandler
{
    /**
     * @param TQuery $query
     *
     * @return TResult
     */
    public function handle(Query $query): mixed;
}
