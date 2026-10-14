<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Domain;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Challenges\Challenge;
use Backendbase\Domain\IdentityAndAccess\Domain\Challenges\ChallengePurpose;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\LoginName;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\SystemAdmin;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\User;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ChallengePolicyTest extends TestCase
{
    public function testConsumedProofCannotBeRepeatedOrUsedForAnotherPurpose(): void
    {
        $id        = IdentityScenario::id();
        $now       = new DateTimeImmutable('2026-10-14T10:00:00Z');
        $challenge = new Challenge(IdentityId::create(), ['accountId' => $id, 'actorId' => $id, 'realm' => 'USER', 'purpose' => ChallengePurpose::OWNERSHIP->value, 'proofHash' => 'proof', 'expiresAt' => '2026-10-14 10:05:00.000000', 'attempts' => 0, 'consumed' => false, 'authVersion' => 1, 'revision' => 1]);
        self::assertFalse($challenge->verify(ChallengePurpose::LOGIN, Realm::USER, true, $now));
        self::assertTrue($challenge->verify(ChallengePurpose::OWNERSHIP, Realm::USER, true, $now));
        self::assertFalse($challenge->verify(ChallengePurpose::OWNERSHIP, Realm::USER, true, $now));
        self::assertSame(1, $challenge->snapshot()['attempts']);
    }

    public function testAdminStateRequiresAnInvitationAndSeparateApproval(): void
    {
        $user = User::create(IdentityId::create(), IdentityId::create(), new LoginName('test.user'), new DateTimeImmutable());
        $this->expectException(AuthenticationDenied::class);
        new SystemAdmin(IdentityId::create(), $user->snapshot());
    }
}
