<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleCatalog\Tests\Adapters\Persistence\Memory;

use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Memory\EntryReadRepository;
use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Memory\EntryStore;
use Backendbase\Domain\ExampleCatalog\Adapters\Persistence\Memory\EntryWriteRepository;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryReadRepository as EntryReadRepositoryContract;
use Backendbase\Domain\ExampleCatalog\Contracts\EntryWriteRepository as EntryWriteRepositoryContract;
use Backendbase\Domain\ExampleCatalog\Domain\Entry;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Domain\ExampleCatalog\Domain\Exception\EntryAlreadyExists;
use Backendbase\Domain\ExampleCatalog\Tests\Adapters\Persistence\EntryRepositoryContract;
use Backendbase\Shared\Exception\ResourceNotFound;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EntryRepositoryTest extends TestCase
{
    use EntryRepositoryContract;

    private EntryReadRepositoryContract $readRepository;
    private EntryWriteRepositoryContract $writeRepository;

    protected function setUp(): void
    {
        $store                 = new EntryStore();
        $this->readRepository  = new EntryReadRepository($store);
        $this->writeRepository = new EntryWriteRepository($store);
    }

    protected function readRepository(): EntryReadRepositoryContract
    {
        return $this->readRepository;
    }

    protected function writeRepository(): EntryWriteRepositoryContract
    {
        return $this->writeRepository;
    }

    #[Test]
    public function itRejectsAddingTheSameEntryTwice(): void
    {
        $entry = Entry::create(
            'example-id',
            EntryType::SYSTEM,
            null,
            'settings',
            true,
            'page-size',
            '25',
            [],
        );
        $this->writeRepository->add($entry);

        $this->expectException(EntryAlreadyExists::class);

        $this->writeRepository->add($entry);
    }

    #[Test]
    public function itRejectsReadingARemovedEntryByIdentifier(): void
    {
        $entry = Entry::create(
            'example-id',
            EntryType::SYSTEM,
            null,
            'settings',
            true,
            'page-size',
            '25',
            [],
        );
        $this->writeRepository->add($entry);
        $entry->remove();
        $this->writeRepository->save($entry);

        $this->expectException(ResourceNotFound::class);

        $this->writeRepository->getActive('example-id');
    }
}
