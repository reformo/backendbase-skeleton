<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineAccountAuthenticationRepository;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineAccountReadRepository;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\DoctrineAccountWriteRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountAuthenticationRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountReadRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\AccountWriteRepository;
use Backendbase\Shared\ServiceProvider as PlatformServiceProvider;
use Override;

final class ServiceProvider implements PlatformServiceProvider
{
    /** @return iterable<class-string, class-string> */
    #[Override]
    public static function getDefinitions(): iterable
    {
        return [
            AccountAuthenticationRepository::class => DoctrineAccountAuthenticationRepository::class,
            AccountReadRepository::class => DoctrineAccountReadRepository::class,
            AccountWriteRepository::class => DoctrineAccountWriteRepository::class,
        ];
    }

    /**
     * @return iterable<int, array{
     *     events: array<int, string>,
     *     subscriberFQCN: class-string,
     *     messageFQCN?: class-string,
     *     eventVersion?: string
     * }>
     */
    #[Override]
    public static function getIntegrationEventSubscribers(): iterable
    {
        return [];
    }
}
