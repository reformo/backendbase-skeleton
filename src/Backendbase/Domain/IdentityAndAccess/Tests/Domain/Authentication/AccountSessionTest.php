<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Domain\Authentication;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountMobile;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\SystemAdmin;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\User;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AccountSessionTest extends TestCase
{
    public function testAcceptedAccountIssuesDistinctSessionAndTokenIdentities(): void
    {
        $id      = IdentityId::create();
        $account = self::account($id);
        $account->acceptRegistration();
        $actor  = User::create(IdentityId::create(), $id, new DateTimeImmutable());
        $first  = $account->startSession(Realm::USER, $actor);
        $second = $account->startSession(Realm::USER, $actor);
        $claims = $first->claims();
        $other  = $second->claims();

        self::assertSame($account->id(), $claims['accountId']);
        self::assertSame($actor->id(), $claims['sub']);
        self::assertSame(1, $claims['authVersion']);
        self::assertSame('USER', $claims['realm']);
        self::assertNotSame($claims['jti'], $claims['sid']);
        self::assertNotSame($claims['sid'], $other['sid']);
        self::assertNotSame($claims['jti'], $other['jti']);
    }

    #[DataProvider('deniedStates')]
    public function testSessionCreationRejectsInvalidAccountAndActorState(string $state): void
    {
        $id      = IdentityId::create();
        $account = self::account($id);
        $now     = new DateTimeImmutable();
        $actor   = User::create(IdentityId::create(), $state === 'account-mismatch' ? IdentityId::create() : $id, $now);
        if ($state !== 'registration-unaccepted') {
            $account->acceptRegistration();
            $account->verifyOwnership();
        }

        if ($state === 'closed') {
            $account->close($now);
        }

        if ($state === 'removed') {
            $actor->remove($now);
        }

        if ($state === 'unapproved') {
            $actor = SystemAdmin::invite(IdentityId::create(), $id, 'invitation:one', $now);
        }

        $realm = $state === 'wrong-realm' || $state === 'unapproved' ? Realm::ADMIN : Realm::USER;
        $this->expectException(AuthenticationDenied::class);
        $account->startSession($realm, $actor);
    }

    /** @return iterable<string, array{string}> */
    public static function deniedStates(): iterable
    {
        foreach (['account-mismatch', 'wrong-realm', 'registration-unaccepted', 'closed', 'removed', 'unapproved'] as $state) {
            yield $state => [$state];
        }
    }

    private static function account(IdentityId $id): Account
    {
        return Account::create($id, new AccountEmail('session@example.com'), new AccountMobile('+905551234567'), new DateTimeImmutable());
    }
}
