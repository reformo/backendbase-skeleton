<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters;

use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SoftDeletionUniquenessTest extends TestCase
{
    public function testRemovedLoginCanBeReusedAndConflictingRestorationRollsBack(): void
    {
        $fixture   = IdentityFixture::create();
        $first     = IdentityScenario::registered($fixture);
        $container = $fixture->container();
        $store     = $container->get(IdentityStore::class);
        $store->transaction(static function () use ($store, $first): void {
            $actor = $store->actorForUpdate(Realm::USER, $first['actorId']);
            $actor->remove(new DateTimeImmutable('2026-10-14T10:01:00Z'));
            $store->saveActor($actor);
        });
        $second = IdentityScenario::registered($fixture, 'ADA.USER', 'second@example.com');
        self::assertNotSame($first['actorId'], $second['actorId']);
        try {
            $store->transaction(static function () use ($store, $first): void {
                $actor = $store->actorForUpdate(Realm::USER, $first['actorId']);
                $actor->restore();
                $store->saveActor($actor);
            });
            self::fail('Restoration must preserve active login uniqueness.');
        } catch (IdentityConflict $exception) {
            self::assertSame('The identity record conflicts with the current state.', $exception->getMessage());
        }

        $connection = $fixture->connection();
        self::assertSame('2026-10-14 10:01:00.000000', $connection->fetchOne('SELECT deleted_at FROM identity_user WHERE uuid = :id', ['id' => $first['actorId']]));
        self::assertSame(2, $connection->fetchOne('SELECT COUNT(*) FROM identity_user'));
    }

    public function testDuplicateLoginRollsBackNewAccountAndChallengeRecords(): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::registered($fixture);
        try {
            IdentityScenario::registered($fixture, 'ADA.USER', 'second@example.com');
            self::fail('An active realm login cannot be reused.');
        } catch (IdentityConflict $exception) {
            self::assertSame('The identity record conflicts with the current state.', $exception->getMessage());
        }

        $connection = $fixture->connection();
        self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM identity_account'));
        self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM identity_user'));
        self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM identity_challenge'));
    }
}
