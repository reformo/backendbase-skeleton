<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Contracts;

use Backendbase\Domain\ExampleBoundedContext\Domain\Example;

interface ExampleWriteRepository
{
    public function add(Example $example): void;

    public function getActive(string $exampleId): Example;

    public function save(Example $example): void;
}
