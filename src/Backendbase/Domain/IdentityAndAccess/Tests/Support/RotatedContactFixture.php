<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Support;

use Backendbase\Domain\IdentityAndAccess\Contracts\ContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Contracts\VerificationLimiter;
use Backendbase\Infrastructure\Configuration\IdentitySettings;
use Backendbase\Shared\Time\Clock;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tests\Infrastructure\Composition\ProductionContainerFixture;

use function base64_encode;
use function str_repeat;

final class RotatedContactFixture
{
    public static function create(IdentityFixture $fixture): IdentityFixture
    {
        $previous   = $fixture->container();
        $connection = $fixture->connection();
        $settings   = IdentityFixture::settings(['contactProofKeyId' => 'new-key', 'contactProofKey' => base64_encode(str_repeat('n', 32)), 'previousContactProofKeyId' => 'factor-key', 'previousContactProofKey' => base64_encode(str_repeat('e', 32))]);
        $container  = ProductionContainerFixture::build([
            Connection::class => $connection,
            Configuration::class => $previous->get(Configuration::class),
            Clock::class => $previous->get(Clock::class),
            LoggerInterface::class => new NullLogger(),
            IdentitySettings::class => $settings,
            ContactNotifier::class => $previous->get(ContactNotifier::class),
            VerificationLimiter::class => $previous->get(VerificationLimiter::class),
            TokenRegistry::class => $previous->get(TokenRegistry::class),
        ]);

        return new IdentityFixture($connection, $container);
    }
}
