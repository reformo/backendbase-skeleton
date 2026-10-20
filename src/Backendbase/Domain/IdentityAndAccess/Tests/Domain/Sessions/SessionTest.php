<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Domain\Sessions;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\SessionState;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RefreshToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\Session;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\SessionPolicy;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Shared\Exception\InvalidUserInput;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function strtoupper;
use function substr;

final class SessionTest extends TestCase
{
    public function testRotationPreservesIdentityAndMaximumAndClampsTheAccessDeadline(): void
    {
        $principal = self::principal();
        $refresh   = RefreshToken::create($principal);
        $now       = new DateTimeImmutable('2026-10-20T10:00:00Z');
        $session   = Session::start($principal, new SessionPolicy(900, 1000, 1200), $now, $refresh);
        $before    = $session->snapshot();
        $restored  = SessionState::fromArray($before->toArray());
        self::assertEquals($before, $restored);
        $replacement = RefreshToken::create($principal);
        $later       = $now->modify('+800 seconds');
        $session->rotate($replacement, $later);
        $after          = $session->snapshot();
        $afterPrincipal = $after->principal;
        $beforeIdentity = $principal->identity();
        $afterIdentity  = $afterPrincipal->identity();
        self::assertSame($beforeIdentity['sid'], $afterIdentity['sid']);
        self::assertNotSame($beforeIdentity['jti'], $afterIdentity['jti']);
        self::assertSame($before->maximumExpiresAt, $after->maximumExpiresAt);
        self::assertNotSame($before->refreshHash, $after->refreshHash);
        $deadline = $session->tokenExpiresAt($later);
        self::assertSame('2026-10-20T10:20:00+00:00', $deadline->format('c'));
    }

    #[DataProvider('expiredIntervals')]
    public function testIdleAndMaximumBoundariesDenyRenewal(string $interval): void
    {
        $principal = self::principal();
        $now       = new DateTimeImmutable('2026-10-20T10:00:00Z');
        $session   = Session::start($principal, new SessionPolicy(60, 120, 180), $now, RefreshToken::create($principal));
        $this->expectException(AuthenticationDenied::class);
        $session->assertActive($now->modify($interval));
    }

    /** @return iterable<string, array{string}> */
    public static function expiredIntervals(): iterable
    {
        yield 'idle boundary' => ['+120 seconds'];
        yield 'maximum boundary' => ['+180 seconds'];
        yield 'before creation' => ['-1 second'];
    }

    #[DataProvider('invalidTokens')]
    public function testRefreshTokenRejectsMalformedCredentials(string $value): void
    {
        $this->expectException(InvalidUserInput::class);
        new RefreshToken($value);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidTokens(): iterable
    {
        yield 'empty' => [''];

        $token = RefreshToken::create(self::principal());

        yield 'truncated' => [substr($token->expose(), 0, -1)];
        yield 'noncanonical secret' => [substr($token->expose(), 0, 74) . '___________________________________________'];
        yield 'invalid identifier' => ['invalid.' . $token->expose()];
        yield 'noncanonical identifiers' => [strtoupper('019a0240-0000-7000-8000-000000000001.019a0240-0000-7000-8000-000000000002.') . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'];
    }

    private static function principal(): Principal
    {
        return new Principal(Realm::USER, ['accountId' => IdentityScenario::id(), 'actorId' => IdentityScenario::id(), 'sid' => IdentityScenario::id(), 'jti' => IdentityScenario::id(), 'authVersion' => 1]);
    }
}
