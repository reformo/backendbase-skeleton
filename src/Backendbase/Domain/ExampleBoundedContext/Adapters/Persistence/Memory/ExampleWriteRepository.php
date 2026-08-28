<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Memory;

use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleWriteRepository as ExampleWriteRepositoryContract;
use Backendbase\Domain\ExampleBoundedContext\Domain\Example;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleIdentity;
use Backendbase\Shared\Exception\ResourceNotFound;

final readonly class ExampleWriteRepository implements ExampleWriteRepositoryContract
{
    public function __construct(private ExampleStore $store)
    {
    }

    public function add(Example $example): void
    {
        $this->store->save($example);
    }

    public function getActive(string $exampleId): Example
    {
        $example = $this->store->get($exampleId);
        if ($example === null || $example->isRemoved()) {
            throw ResourceNotFound::create('The example was not found.');
        }

        return $example;
    }

    public function getActiveByIdentity(ExampleIdentity $identity): Example
    {
        $example = $this->store->getByIdentity($identity);
        if ($example === null) {
            throw ResourceNotFound::create('The example was not found.');
        }

        return $example;
    }

    public function save(Example $example): void
    {
        $this->store->save($example);
    }
}
