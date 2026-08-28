<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Memory;

use Backendbase\Domain\ExampleBoundedContext\Domain\Example;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleIdentity;
use Backendbase\Domain\ExampleBoundedContext\Domain\Exception\ExampleAlreadyExists;

final class ExampleStore
{
    /** @var array<string, Example> */
    private array $examples = [];

    public function add(Example $example): void
    {
        if (isset($this->examples[$example->id()])) {
            throw ExampleAlreadyExists::create('The example already exists.');
        }

        $this->save($example);
    }

    public function save(Example $example): void
    {
        $this->rejectActiveIdentityConflict($example);
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

    private function rejectActiveIdentityConflict(Example $example): void
    {
        if ($example->isRemoved()) {
            return;
        }

        $state    = $example->snapshot();
        $identity = new ExampleIdentity(
            $state['type'],
            $state['typeTargetId'],
            $state['group'],
            $state['lookupKey'],
        );
        foreach ($this->examples as $storedExample) {
            $this->rejectConflictWithStoredExample($example, $storedExample, $identity);
        }
    }

    private function rejectConflictWithStoredExample(
        Example $example,
        Example $storedExample,
        ExampleIdentity $identity,
    ): void {
        if ($storedExample->id() === $example->id() || $storedExample->isRemoved()) {
            return;
        }

        if (! $storedExample->hasIdentity($identity)) {
            return;
        }

        throw ExampleAlreadyExists::create('An active example already uses this identity.');
    }
}
