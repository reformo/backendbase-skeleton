<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\IdentityAccount;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class GeneratedProjectionSafetyTest extends TestCase
{
    #[DataProvider('recordTypes')]
    public function testHydratedGeneratedProjectionsMustMatchTheAuthoritativeFields(string $kind): void
    {
        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $manager   = $container->get(EntityManagerInterface::class);
        $unit      = $manager->getUnitOfWork();
        $id        = Uuid::uuid7();
        $common    = ['id' => $id, 'uuid' => $id->toString(), 'version' => 1, 'createdAt' => new DateTimeImmutable('2026-10-14T10:00:00Z'), 'deletedAt' => null];
        $record    = $unit->createEntity(IdentityAccount::class, [...$common, 'email' => 'user@example.com', 'activeEmail' => $kind === 'email' ? 'wrong@example.com' : 'user@example.com', 'mobile' => '+905551234567', 'activeMobile' => $kind === 'mobile' ? '+905551234568' : '+905551234567', 'state' => ['ownershipVerified' => true, 'enabled' => true, 'authVersion' => 1]]);
        self::assertInstanceOf(IdentityAccount::class, $record);
        $this->expectException(AuthenticationUnavailable::class);
        $record->toDomain();
    }

    /** @return iterable<string, array{string}> */
    public static function recordTypes(): iterable
    {
        yield 'Account email' => ['email'];
        yield 'Account mobile' => ['mobile'];
    }
}
