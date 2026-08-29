<?php

declare(strict_types=1);

namespace Tests\Domain\IdentityAndAccess;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineAccountAuthenticationRepository;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineAccountReadRepository;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineAccountWriteRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthenticationRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountReadRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountWriteRepository;
use Backendbase\Domain\IdentityAndAccess\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ServiceProviderTest extends TestCase
{
    #[Test]
    public function itProvidesIdentityAndAccessRepositories(): void
    {
        self::assertSame([
            AccountAuthenticationRepository::class => DoctrineAccountAuthenticationRepository::class,
            AccountReadRepository::class => DoctrineAccountReadRepository::class,
            AccountWriteRepository::class => DoctrineAccountWriteRepository::class,
        ], ServiceProvider::getDefinitions());
    }
}
