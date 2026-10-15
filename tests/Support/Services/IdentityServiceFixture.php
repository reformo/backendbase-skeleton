<?php

declare(strict_types=1);

namespace Tests\Support\Services;

use Backendbase\Domain\IdentityAndAccess\Contracts\ContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Contracts\VerificationLimiter;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\CapturedContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\MemoryTokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\MemoryVerificationLimiter;
use Backendbase\Infrastructure\Configuration\IdentitySettings;
use Backendbase\Shared\Time\Clock;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\ORMSetup;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tests\Infrastructure\Composition\ProductionContainerFixture;
use Tests\Support\Time\FrozenClock;

final class IdentityServiceFixture
{
    public static function create(Connection $connection): IdentityFixture
    {
        $configuration = ORMSetup::createAttributeMetadataConfiguration(['src/Backendbase/Domain/IdentityAndAccess/Adapters/Persistence/Doctrine/Entity'], true);
        $configuration->enableNativeLazyObjects(true);
        $clock     = new FrozenClock(new DateTimeImmutable('2026-10-14T10:00:00Z'));
        $container = ProductionContainerFixture::build([
            Connection::class => $connection,
            Configuration::class => $configuration,
            LoggerInterface::class => new NullLogger(),
            Clock::class => $clock,
            IdentitySettings::class => IdentityFixture::settings(),
            ContactNotifier::class => new CapturedContactNotifier(),
            VerificationLimiter::class => new MemoryVerificationLimiter($clock),
            TokenRegistry::class => new MemoryTokenRegistry($clock),
        ]);

        return new IdentityFixture($connection, $container);
    }
}
