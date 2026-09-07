<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Tests\Adapters\Persistence;

use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleReadRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleWriteRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleByCriteria;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleGroupsByType;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleIdByCriteria;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExamplesByGroup;
use Backendbase\Domain\ExampleBoundedContext\Domain\Example;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleIdentity;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
use Backendbase\Domain\ExampleBoundedContext\Domain\Exception\ExampleAlreadyExists;
use Backendbase\Shared\Exception\ResourceNotFound;
use Backendbase\Shared\Primitives\Pagination;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

trait ExampleRepositoryContract
{
    abstract protected function readRepository(): ExampleReadRepository;

    abstract protected function writeRepository(): ExampleWriteRepository;

    #[Test]
    public function itReturnsEmptyResultsFromAnEmptyRepository(): void
    {
        self::assertNull($this->readRepository()->getExampleByCriteria(
            new GetExampleByCriteria(ExampleType::SYSTEM, null, 'settings', 'missing'),
        ));
        $page = $this->readRepository()->getExamplesByGroup(
            new GetExamplesByGroup(ExampleType::SYSTEM, null, 'settings', new Pagination(10, 1)),
        );
        self::assertSame(0, $page->total());
    }

    #[Test]
    public function itWritesAndReadsExampleProjections(): void
    {
        $firstExampleId  = Uuid::uuid7()->toString();
        $secondExampleId = Uuid::uuid7()->toString();
        $thirdExampleId  = Uuid::uuid7()->toString();
        $fourthExampleId = Uuid::uuid7()->toString();
        $this->addExample($firstExampleId, 'settings', 'first', 'one');
        $this->addExample($secondExampleId, 'settings', 'second', 'two');
        $this->addExample($thirdExampleId, 'features', 'third', 'three');
        $this->addExample($fourthExampleId, 'features', 'targeted', 'four', 42);

        $groups = $this->readRepository()->getExampleGroupsByType(
            new GetExampleGroupsByType(ExampleType::SYSTEM, null),
        );
        self::assertSame(['features', 'settings'], $groups);

        $page = $this->readRepository()->getExamplesByGroup(
            new GetExamplesByGroup(ExampleType::SYSTEM, null, 'settings', new Pagination(1, 2)),
        );
        self::assertSame(2, $page->total());
        self::assertSame($secondExampleId, $page->items()[0]->uuid());

        $details = $this->readRepository()->getExampleByCriteria(
            new GetExampleByCriteria(ExampleType::SYSTEM, 42, 'features', 'targeted'),
        );
        self::assertNotNull($details);
        self::assertSame('four', $details->lookupValue());
        self::assertSame(['image' => 'example.png'], $details->details());
    }

    #[Test]
    public function itChangesAndSoftDeletesOnlyActiveExamples(): void
    {
        $exampleId = Uuid::uuid7()->toString();
        $this->addExample($exampleId, 'settings', 'first', 'one');
        $identity = new ExampleIdentity(ExampleType::SYSTEM, null, 'settings', 'first');
        $example  = $this->writeRepository()->getActiveByIdentity($identity);
        self::assertSame($exampleId, $example->id());
        $example->change(false, 'changed', ['changed' => true]);
        $this->writeRepository()->save($example);

        $details = $this->readRepository()->getExampleByCriteria(
            new GetExampleByCriteria(ExampleType::SYSTEM, null, 'settings', 'first'),
        );
        self::assertNotNull($details);
        self::assertSame('changed', $details->lookupValue());
        self::assertFalse($details->isActive());
        self::assertSame(['changed' => true], $details->details());

        $example = $this->writeRepository()->getActive($exampleId);
        $example->remove();
        $this->writeRepository()->save($example);
        self::assertNull($this->readRepository()->getExampleIdByCriteria(
            new GetExampleIdByCriteria(ExampleType::SYSTEM, null, 'settings', 'first'),
        ));

        $this->expectException(ResourceNotFound::class);
        $this->writeRepository()->getActiveByIdentity($identity);
    }

    #[Test]
    public function itRejectsDuplicateActiveExamplesWithoutATypeTarget(): void
    {
        $this->addExample(Uuid::uuid7()->toString(), 'settings', 'first', 'one');

        $this->expectException(ExampleAlreadyExists::class);
        $this->addExample(Uuid::uuid7()->toString(), 'settings', 'first', 'duplicate');
    }

    #[Test]
    public function itAllowsAReplacementAfterTheExistingExampleIsSoftDeleted(): void
    {
        $firstExampleId  = Uuid::uuid7()->toString();
        $secondExampleId = Uuid::uuid7()->toString();
        $this->addExample($firstExampleId, 'settings', 'first', 'one');
        $example = $this->writeRepository()->getActive($firstExampleId);
        $example->remove();
        $this->writeRepository()->save($example);

        $this->addExample($secondExampleId, 'settings', 'first', 'replacement');

        $storedExampleId = $this->readRepository()->getExampleIdByCriteria(
            new GetExampleIdByCriteria(ExampleType::SYSTEM, null, 'settings', 'first'),
        );
        self::assertSame($secondExampleId, $storedExampleId);
    }

    protected function addExample(
        string $exampleId,
        string $group,
        string $key,
        string $value,
        int|null $typeTargetId = null,
    ): void {
        $this->writeRepository()->add(Example::create(
            $exampleId,
            ExampleType::SYSTEM,
            $typeTargetId,
            $group,
            true,
            $key,
            $value,
            ['image' => 'example.png'],
        ));
    }
}
