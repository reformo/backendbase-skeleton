<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Tests\Domain;

use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Doctrine\Entity\ExampleRecord;
use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Memory\ExampleStore;
use Backendbase\Domain\ExampleBoundedContext\Domain\Example;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ExampleTest extends TestCase
{
    #[Test]
    public function itCreatesAValidExample(): void
    {
        $example = Example::create(
            'example-id',
            ExampleType::SYSTEM,
            null,
            'settings',
            true,
            'page-size',
            '25',
            ['unit' => 'items'],
        );
        $state   = $example->snapshot();

        self::assertSame('example-id', $example->id());
        self::assertSame(ExampleType::SYSTEM, $state['type']);
        self::assertSame('25', $state['lookupValue']);
        self::assertSame(['unit' => 'items'], $state['details']);
        self::assertTrue($state['isActive']);
        self::assertSame($state['createdAt'], $state['updatedAt']);
        self::assertFalse($example->isRemoved());
    }

    #[Test]
    public function itOwnsChangeAndRemovalRules(): void
    {
        $initialDate = new DateTimeImmutable('2026-01-01 00:00:00');
        $example     = Example::reconstitute(
            'example-id',
            ExampleType::SYSTEM,
            null,
            'settings',
            'page-size',
            '25',
            ['unit' => 'items'],
            true,
            $initialDate,
            $initialDate,
            null,
        );

        $example->change(false, null, ['unit' => 'rows']);
        $changedState = $example->snapshot();

        self::assertFalse($changedState['isActive']);
        self::assertSame('25', $changedState['lookupValue']);
        self::assertSame(['unit' => 'rows'], $changedState['details']);
        self::assertGreaterThan($initialDate, $changedState['updatedAt']);

        $example->remove();
        $removedState = $example->snapshot();

        self::assertTrue($example->isRemoved());
        self::assertNotNull($removedState['removedAt']);
        self::assertSame($removedState['removedAt'], $removedState['updatedAt']);
    }

    #[Test]
    public function itReturnsNullForAnUnknownStoredExample(): void
    {
        self::assertNull((new ExampleStore())->get('missing-id'));
    }

    #[Test]
    public function itExposesThePersistedRecordIdentifier(): void
    {
        $example  = Example::create(
            'example-id',
            ExampleType::SYSTEM,
            null,
            'settings',
            true,
            'page-size',
            '25',
            [],
        );
        $record   = ExampleRecord::fromDomain($example);
        $property = new ReflectionProperty($record, 'id');
        $property->setValue($record, 42);

        self::assertSame(42, $record->id());
    }
}
