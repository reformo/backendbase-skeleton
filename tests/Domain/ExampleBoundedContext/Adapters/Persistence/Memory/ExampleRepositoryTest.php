<?php

declare(strict_types=1);

namespace Tests\Domain\ExampleBoundedContext\Adapters\Persistence\Memory;

use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Memory\ExampleReadRepository;
use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Memory\ExampleStore;
use Backendbase\Domain\ExampleBoundedContext\Adapters\Persistence\Memory\ExampleWriteRepository;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleReadRepository as ExampleReadRepositoryContract;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ExampleWriteRepository as ExampleWriteRepositoryContract;
use PHPUnit\Framework\TestCase;
use Tests\Domain\ExampleBoundedContext\Adapters\Persistence\ExampleRepositoryContract;

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
}
