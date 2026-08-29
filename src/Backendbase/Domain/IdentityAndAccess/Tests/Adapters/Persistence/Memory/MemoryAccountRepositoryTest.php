<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Persistence\Memory;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Memory\MemoryAccountRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthenticationRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountReadRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountWriteRepository;
use Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Persistence\AccountRepositoryContract;
use Backendbase\Shared\Exception\ResourceNotFound;
use PHPUnit\Framework\Attributes\Test;

final class MemoryAccountRepositoryTest extends AccountRepositoryContract
{
    private MemoryAccountRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new MemoryAccountRepository();
    }

    protected function authenticationRepository(): AccountAuthenticationRepository
    {
        return $this->repository;
    }

    protected function readRepository(): AccountReadRepository
    {
        return $this->repository;
    }

    protected function writeRepository(): AccountWriteRepository
    {
        return $this->repository;
    }

    #[Test]
    public function itRejectsSavingAMissingAccount(): void
    {
        $this->expectException(ResourceNotFound::class);

        $this->repository->save($this->account('5bb3fe29-8b80-463e-9d42-b3a9298a7586'));
    }
}
