<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Memory;

use Backendbase\Domain\ExampleBoundedContext\Domain\Example;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleIdentity;

final class ExampleStore
{
    /** @var array<string, Example> */
    private array $examples = [];

    public function save(Example $example): void
    {
        $this->examples[$example->id()] = clone $example;
    }

    public function get(string $exampleId): Example|null
    {
        if (! isset($this->examples[$exampleId])) {
            return null;
        }

        return clone $this->examples[$exampleId];
    }

    public function getByIdentity(ExampleIdentity $identity): Example|null
    {
        foreach ($this->examples as $example) {
            if ($example->isRemoved()) {
                continue;
            }

            if (! $example->hasIdentity($identity)) {
                continue;
            }

            return clone $example;
        }

        return null;
    }

    /** @return list<Example> */
    public function all(): array
    {
        $examples = [];
        foreach ($this->examples as $example) {
            $examples[] = clone $example;
        }

        return $examples;
    }
}
