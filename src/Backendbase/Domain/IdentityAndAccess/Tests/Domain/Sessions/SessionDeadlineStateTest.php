<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Domain\Sessions;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RefreshToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\Session;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\SessionPolicy;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SessionDeadlineStateTest extends TestCase
{
    public function testIdleDeadlineIsExclusive(): void
    {
        $principal      = self::principal();
        $now            = new DateTimeImmutable('2026-10-20T10:00:00Z');
        $session        = Session::start($principal, new SessionPolicy(60, 120, 180), $now, RefreshToken::create($principal));
        $beforeDeadline = $now->modify('+119 seconds');
        $deadline       = $now->modify('+120 seconds');
        $session->assertActive($beforeDeadline);

        $this->expectException(AuthenticationDenied::class);
        $session->assertActive($deadline);
    }

    public function testMaximumDeadlineRemainsExclusiveAfterRotation(): void
    {
        $principal    = self::principal();
        $now          = new DateTimeImmutable('2026-10-20T10:00:00Z');
        $session      = Session::start($principal, new SessionPolicy(60, 120, 180), $now, RefreshToken::create($principal));
        $before       = $session->snapshot();
        $rotationTime = $now->modify('+119 seconds');
        $session->rotate(RefreshToken::create($principal), $rotationTime);
        $after = $session->snapshot();
        self::assertSame($before->maximumExpiresAt, $after->maximumExpiresAt);
        $beforeDeadline = $now->modify('+179 seconds');
        $deadline       = $now->modify('+180 seconds');
        $session->assertActive($beforeDeadline);

        $this->expectException(AuthenticationDenied::class);
        $session->assertActive($deadline);
    }

    public function testDomainClockBeforeCreationIsRejected(): void
    {
        $principal = self::principal();
        $now       = new DateTimeImmutable('2026-10-20T10:00:00Z');
        $session   = Session::start($principal, new SessionPolicy(60, 120, 180), $now, RefreshToken::create($principal));

        $beforeCreation = $now->modify('-1 second');
        $this->expectException(AuthenticationDenied::class);
        $session->assertActive($beforeCreation);
    }

    public function testDomainAllowsAClockBetweenCreationAndTheLastRotation(): void
    {
        $principal    = self::principal();
        $now          = new DateTimeImmutable('2026-10-20T10:00:00Z');
        $session      = Session::start($principal, new SessionPolicy(60, 120, 180), $now, RefreshToken::create($principal));
        $rotationTime = $now->modify('+90 seconds');
        $earlierTime  = $now->modify('+30 seconds');
        $session->rotate(RefreshToken::create($principal), $rotationTime);
        $session->assertActive($earlierTime);
        $state = $session->snapshot();
        self::assertSame($rotationTime->getTimestamp(), $state->lastUsedAt);
    }

    private static function principal(): Principal
    {
        return new Principal(Realm::USER, ['accountId' => IdentityScenario::id(), 'actorId' => IdentityScenario::id(), 'sid' => IdentityScenario::id(), 'jti' => IdentityScenario::id(), 'authVersion' => 1]);
    }
}
