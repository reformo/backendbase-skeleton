<?php

declare(strict_types=1);

namespace Backendbase\Shared\CQRS;

interface QueryBus
{
    /**
     * @param Query<TResult> $query
     *
     * @return TResult
     *
     * @template TResult
     */
    public function handle(Query $query): mixed;
}
