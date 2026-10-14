<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\IdentityAdmin;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\IdentityChallenge;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\IdentityUser;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Challenges\Challenge;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\LoginName;
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
        $loginName = new LoginName('revision.actor');
        $now       = new DateTimeImmutable('2026-10-15T10:00:00Z');
        $actor     = $realm === Realm::USER
            ? User::create($actorId, $accountId, $loginName, $now)
            : SystemAdmin::invite($actorId, $accountId, $loginName, 'invitation', $now);
        $record    = $actor instanceof User ? IdentityUser::create($actor) : IdentityAdmin::create($actor);
        $before    = $actor->snapshot();
        $stale     = $actor instanceof User
            ? new User($actorId, [...$before, 'revision' => 2, 'loginName' => 'changed.actor'])
            : new SystemAdmin($actorId, [...$before, 'revision' => 2, 'loginName' => 'changed.actor']);
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

    public function testAStaleChallengeCannotConsumeTheCurrentProof(): void
    {
        $id                = IdentityId::create();
        $accountId         = IdentityId::create();
        $actorId           = IdentityId::create();
        $accountIdentifier = $accountId->toString();
        $actorIdentifier   = $actorId->toString();
        $state             = ['accountId' => $accountIdentifier, 'actorId' => $actorIdentifier, 'realm' => 'USER', 'purpose' => 'ownership', 'proofHash' => 'proof', 'expiresAt' => '2026-10-15 10:05:00.000000', 'attempts' => 0, 'consumed' => false, 'authVersion' => 1, 'revision' => 1];
        $record            = IdentityChallenge::create(new Challenge($id, $state));
        $original          = $record->toDomain();
        $before            = $original->snapshot();
        $stale             = new Challenge($id, [...$state, 'revision' => 2, 'consumed' => true]);
        try {
            $record->synchronize($stale);
            self::fail('A stale challenge revision must be rejected.');
        } catch (IdentityConflict $exception) {
            self::assertSame('The identity record changed. Reload the record before another attempt.', $exception->getMessage());
        }

        $unchanged = $record->toDomain();
        self::assertSame($before, $unchanged->snapshot());
    }
}
