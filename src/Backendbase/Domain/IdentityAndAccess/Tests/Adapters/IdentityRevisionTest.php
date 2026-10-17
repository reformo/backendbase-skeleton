<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\Admin as AdminRecord;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\User as UserRecord;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\SystemAdmin;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\User;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdentityRevisionTest extends TestCase
{
    #[DataProvider('actorRealms')]
    public function testStaleActorStateCannotReplaceTheCurrentRecord(Realm $realm): void
    {
        $actorId   = IdentityId::create();
        $accountId = IdentityId::create();
        $now       = new DateTimeImmutable('2026-10-15T10:00:00Z');
        $actor     = $realm === Realm::USER
            ? User::create($actorId, $accountId, $now)
            : SystemAdmin::invite($actorId, $accountId, 'invitation', $now);
        $record    = $actor instanceof User ? UserRecord::create($actor) : AdminRecord::create($actor);
        $before    = $actor->snapshot();
        $stale     = $actor instanceof User
            ? new User($actorId, [...$before, 'revision' => 2, 'approved' => false])
            : new SystemAdmin($actorId, [...$before, 'revision' => 2, 'approved' => false]);
        try {
            $record->synchronize($stale);
            self::fail('A stale actor revision must be rejected.');
        } catch (IdentityConflict $exception) {
            self::assertSame('The identity record changed. Reload the record before another attempt.', $exception->getMessage());
        }

        $unchanged = $record->toDomain();
        self::assertSame($before, $unchanged->snapshot());
    }

    /** @return iterable<string, array{Realm}> */
    public static function actorRealms(): iterable
    {
        yield 'User' => [Realm::USER];
        yield 'Admin' => [Realm::ADMIN];
    }
}
