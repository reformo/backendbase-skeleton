<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Memory;

use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleReadRepository as ExampleReadRepositoryContract;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleByCriteria;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleGroupsByType;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleIdByCriteria;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExamplesByGroup;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ReadModel\ExampleDetails;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ReadModel\ExampleListItem;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ReadModel\ExamplePage;
use Backendbase\Domain\ExampleBoundedContext\Domain\Example;

use function array_keys;
use function array_slice;
use function count;
use function sort;

final readonly class ExampleReadRepository implements ExampleReadRepositoryContract
{
    public function __construct(private ExampleStore $store)
    {
    }

    public function getExampleIdByCriteria(GetExampleIdByCriteria $query): string|null
    {
        $example = $this->find($query);

        return $example?->id();
    }

    public function getExampleByCriteria(GetExampleByCriteria $query): ExampleDetails|null
    {
        $example = $this->find($query);
        if ($example === null) {
            return null;
        }

        $state = $example->snapshot();

        return new ExampleDetails(
            $state['uuid'],
            $state['type'],
            $state['typeTargetId'],
            $state['group'],
            $state['lookupKey'],
            $state['lookupValue'],
            $state['details'],
            $state['isActive'],
            $state['updatedAt'],
            $state['createdAt'],
        );
    }

    /** @return list<string> */
    public function getExampleGroupsByType(GetExampleGroupsByType $query): array
    {
        $groups = [];
        foreach ($this->store->all() as $example) {
            if ($example->isRemoved()) {
                continue;
            }

            $state = $example->snapshot();
            if ($state['type'] !== $query->type() || $state['typeTargetId'] !== $query->typeTargetId()) {
                continue;
            }

            $groups[$state['group']] = true;
        }

        $groupNames = array_keys($groups);
        sort($groupNames);

        return $groupNames;
    }

    public function getExamplesByGroup(GetExamplesByGroup $query): ExamplePage
    {
        $matchingExamples = [];
        foreach ($this->store->all() as $example) {
            if ($example->isRemoved()) {
                continue;
            }

            $state = $example->snapshot();
            if (
                $state['type'] !== $query->type()
                || $state['group'] !== $query->group()
                || $state['typeTargetId'] !== $query->typeTargetId()
            ) {
                continue;
            }

            $matchingExamples[] = $state;
        }

        $total        = count($matchingExamples);
        $pageExamples = array_slice(
            $matchingExamples,
            $query->pagination()->getOffset(),
            $query->pagination()->pageSize(),
        );
        $items        = [];
        foreach ($pageExamples as $example) {
            $items[] = new ExampleListItem(
                $example['uuid'],
                $example['lookupKey'],
                $example['lookupValue'],
                $example['details'],
                $example['isActive'],
                $example['createdAt'],
            );
        }

        return new ExamplePage($items, $total);
    }

    private function find(GetExampleByCriteria|GetExampleIdByCriteria $query): Example|null
    {
        foreach ($this->store->all() as $example) {
            if ($example->isRemoved()) {
                continue;
            }

            $state = $example->snapshot();
            if (
                $state['type'] === $query->type()
                && $state['group'] === $query->group()
                && $state['lookupKey'] === $query->key()
                && $state['typeTargetId'] === $query->typeTargetId()
            ) {
                return $example;
            }
        }

        return null;
    }
}
