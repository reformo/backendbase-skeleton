<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Contracts;

use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleByCriteria;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleGroupsByType;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleIdByCriteria;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExamplesByGroup;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ReadModel\ExampleDetails;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ReadModel\ExamplePage;

interface ExampleReadRepository
{
    public function getExampleByCriteria(GetExampleByCriteria $query): ExampleDetails|null;

    public function getExampleIdByCriteria(GetExampleIdByCriteria $query): string|null;

    public function getExamplesByGroup(GetExamplesByGroup $query): ExamplePage;

    /** @return list<string> */
    public function getExampleGroupsByType(GetExampleGroupsByType $query): array;
}
