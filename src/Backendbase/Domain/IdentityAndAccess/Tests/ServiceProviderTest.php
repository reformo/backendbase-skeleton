<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Jwt;
use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\JwtAuthorizationStore;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineAccountAuthenticationRepository;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineAccountReadRepository;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineAccountWriteRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthenticationRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthorizationState;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountReadRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountWriteRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\AuthorizationStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenIssuer;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenValidator;
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
            AccountAuthorizationState::class => JwtAuthorizationStore::class,
            AccountReadRepository::class => DoctrineAccountReadRepository::class,
            AccountWriteRepository::class => DoctrineAccountWriteRepository::class,
            AuthorizationStore::class => JwtAuthorizationStore::class,
            TokenIssuer::class => Jwt::class,
            TokenValidator::class => Jwt::class,
        ], ServiceProvider::getDefinitions());
    }
}
