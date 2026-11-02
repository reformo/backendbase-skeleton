<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Domain\Authentication;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\SystemAdmin;
use Backendbase\Shared\Exception\InvalidUserInput;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ActorLifecycleTest extends TestCase
{
    #[DataProvider('adminStates')]
    public function testApprovalAndRemovalHaveSeparateGuards(bool $approved, bool $removed, bool $session, bool $contact, bool $pending): void
    {
        $id    = IdentityId::create();
        $admin = SystemAdmin::invite($id, IdentityId::create(), 'ticket:invitation', new DateTimeImmutable('2026-10-19T10:00:00Z'));
        if ($approved) {
            $admin->approve('ticket:approval');
        }

        if ($removed) {
            $admin->remove(new DateTimeImmutable('2026-10-19T11:00:00Z'));
        }

        $snapshot      = $admin->snapshot();
        $reconstructed = new SystemAdmin($id, $snapshot->toArray());
        self::assertDecision($reconstructed->assertSessionAllowed(...), $session, AuthenticationDenied::class);
        self::assertDecision($reconstructed->assertContactVerificationAllowed(...), $contact, AuthenticationDenied::class);
        self::assertDecision($reconstructed->assertPendingApproval(...), $pending, IdentityConflict::class);
    }

    /** @return iterable<string, array{bool, bool, bool, bool, bool}> */
    public static function adminStates(): iterable
    {
        yield 'pending and present' => [false, false, false, true, true];
        yield 'pending and removed' => [false, true, false, false, false];
        yield 'approved and present' => [true, false, true, true, false];
        yield 'approved and removed' => [true, true, false, false, false];
    }

    public function testRepeatedRemovalAndRestorationPreserveApprovalEvidence(): void
    {
        $id    = IdentityId::create();
        $admin = SystemAdmin::invite($id, IdentityId::create(), 'ticket:invitation', new DateTimeImmutable('2026-10-19T10:00:00Z'));
        $admin->approve('ticket:approval');
        $admin->remove(new DateTimeImmutable('2026-10-19T11:00:00Z'));
        $admin->remove(new DateTimeImmutable('2026-10-19T12:00:00Z'));
        $removed = $admin->snapshot();
        self::assertSame('2026-10-19 12:00:00.000000', $removed->deletedAt);
        $admin = new SystemAdmin($id, $removed->toArray());
        $admin->restore();
        $admin->restore();
        $admin->assertSessionAllowed();
        $restored      = $admin->snapshot();
        $authorization = $restored->authorization;
        self::assertNull($restored->deletedAt);
        self::assertTrue($authorization->approved);
        self::assertSame('ticket:invitation', $authorization->invitation);
        self::assertSame('ticket:approval', $authorization->approval);
    }

    public function testReapprovalReplacesEvidenceAndInvalidEvidencePreservesState(): void
    {
        $admin = SystemAdmin::invite(IdentityId::create(), IdentityId::create(), 'ticket:invitation', new DateTimeImmutable('2026-10-19T10:00:00Z'));
        $admin->approve('ticket:first-approval');
        $before = $admin->snapshot();
        try {
            $admin->approve('ticket:invitation');
            self::fail('Matching evidence must be rejected.');
        } catch (InvalidUserInput) {
            $after       = $admin->snapshot();
            $beforeState = $before->toArray();
            self::assertSame($beforeState, $after->toArray());
        }

        $admin->approve('ticket:second-approval');
        $state         = $admin->snapshot();
        $authorization = $state->authorization;
        self::assertTrue($authorization->approved);
        self::assertSame('ticket:second-approval', $authorization->approval);
    }

    /** @param class-string<AuthenticationDenied|IdentityConflict> $exceptionClass */
    private static function assertDecision(callable $decision, bool $allowed, string $exceptionClass): void
    {
        try {
            $decision();
            self::assertTrue($allowed);
        } catch (AuthenticationDenied | IdentityConflict $exception) {
            self::assertFalse($allowed);
            self::assertInstanceOf($exceptionClass, $exception);
        }
    }
}
