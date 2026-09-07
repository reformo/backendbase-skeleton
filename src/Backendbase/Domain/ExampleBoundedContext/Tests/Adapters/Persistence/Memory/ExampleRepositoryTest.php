<?php

declare(strict_types=1);

namespace Backendbase\Domain\ExampleBoundedContext\Tests\Adapters\Persistence\Memory;

use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Memory\ExampleReadRepository;
use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Memory\ExampleStore;
use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Memory\ExampleWriteRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleReadRepository as ExampleReadRepositoryContract;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleWriteRepository as ExampleWriteRepositoryContract;
use Backendbase\Domain\ExampleBoundedContext\Domain\Example;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
use Backendbase\Domain\ExampleBoundedContext\Domain\Exception\ExampleAlreadyExists;
use Backendbase\Domain\ExampleBoundedContext\Tests\Adapters\Persistence\ExampleRepositoryContract;
use Backendbase\Shared\Exception\ResourceNotFound;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExampleRepositoryTest extends TestCase
{
    use ExampleRepositoryContract;

    private ExampleReadRepositoryContract $readRepository;
    private ExampleWriteRepositoryContract $writeRepository;

    protected function setUp(): void
    {
        $store                 = new ExampleStore();
        $this->readRepository  = new ExampleReadRepository($store);
        $this->writeRepository = new ExampleWriteRepository($store);
    }

    protected function readRepository(): ExampleReadRepositoryContract
    {
        return $this->readRepository;
    }

    protected function writeRepository(): ExampleWriteRepositoryContract
    {
        return $this->writeRepository;
    }

    #[Test]
    public function itRejectsAddingTheSameExampleTwice(): void
    {
        $example = Example::create(
            'example-id',
            ExampleType::SYSTEM,
            null,
            'settings',
            true,
            'page-size',
            '25',
            [],
        );
        $this->writeRepository->add($example);

        $this->expectException(ExampleAlreadyExists::class);

        $this->writeRepository->add($example);
    }

    #[Test]
    public function itRejectsReadingARemovedExampleByIdentifier(): void
    {
        $example = Example::create(
            'example-id',
            ExampleType::SYSTEM,
            null,
            'settings',
            true,
            'page-size',
            '25',
            [],
        );
        $this->writeRepository->add($example);
        $example->remove();
        $this->writeRepository->save($example);

        $this->expectException(ResourceNotFound::class);

        $this->writeRepository->getActive('example-id');
    }
}
