<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Contracts;

use Backendbase\Domain\ExampleBoundedContext\Domain\Example;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleIdentity;

interface ExampleWriteRepository
{
    public function add(Example $example): void;

    public function getActive(string $exampleId): Example;

    public function getActiveByIdentity(ExampleIdentity $identity): Example;

    public function save(Example $example): void;
}
