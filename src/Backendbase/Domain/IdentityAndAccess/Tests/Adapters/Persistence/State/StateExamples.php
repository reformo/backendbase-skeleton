<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Persistence\State;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\Account as AccountRecord;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\Admin as AdminRecord;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\ContactVerification;
use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\Entity\User as UserRecord;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo as Contact;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountMobile;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\SystemAdmin;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\User;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\AccountState;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\ActorState;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\ContactState;
use DateTimeImmutable;

final class StateExamples
{
    public const array ACCOUNT = ['ownershipVerified' => false, 'enabled' => true, 'authVersion' => 1];
    public const array ACTOR   = ['approved' => false, 'invitation' => 'invitation', 'approval' => null];
    public const array CONTACT = [
        'realm' => 'USER',
        'purpose' => 'registration',
        'contactType' => 'email',
        'contactValue' => 'ada@example.com',
        'contextHash' => 'context',
        'accountId' => null,
        'actorId' => null,
        'generationId' => '',
        'codeHash' => '',
        'codeExpiresAt' => '2026-10-17 10:05:00.000000',
        'failedAttempts' => 0,
        'requestedAt' => '2026-10-17 10:00:00.000000',
        'lastSentAt' => '2026-10-17 10:00:00.000000',
        'verifiedAt' => null,
        'consumedAt' => null,
        'deliveryState' => 'pending',
        'revision' => 1,
    ];

    /** @return iterable<string, array{class-string<AccountState|ActorState|ContactState>, array<string, mixed>}> */
    public static function values(): iterable
    {
        yield 'Account' => [AccountState::class, self::ACCOUNT];
        yield 'Actor' => [ActorState::class, self::ACTOR];
        yield 'Contact' => [ContactState::class, self::CONTACT];
    }

    public static function record(string $kind): AccountRecord|UserRecord|AdminRecord|ContactVerification
    {
        $id        = IdentityId::create();
        $accountId = IdentityId::create();
        $now       = new DateTimeImmutable('2026-10-17T10:00:00Z');

        return match ($kind) {
            'account' => AccountRecord::create(Account::create($id, new AccountEmail('ada@example.com'), new AccountMobile('+905551234567'), $now)),
            'user' => UserRecord::create(User::create($id, $accountId, $now)),
            'admin' => AdminRecord::create(SystemAdmin::invite($id, $accountId, 'invitation', $now)),
            default => ContactVerification::create(new Contact($id, self::CONTACT)),
        };
    }
}
