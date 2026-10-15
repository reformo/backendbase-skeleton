<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Domain;

use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Administration\ActorChangeInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Administration\AdminApprovalInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Administration\AdminInvitationInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\Query\GetActorHistory;
use Backendbase\Domain\IdentityAndAccess\Contracts\Query\GetCurrentIdentity;
use Backendbase\Domain\IdentityAndAccess\Contracts\Query\GetIdentityOperation;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\SessionLifetime;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\SystemAdmin;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Shared\Exception\InvalidUserInput;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

final class IdentityPolicyTest extends TestCase
{
    /** @param class-string<Throwable> $exception */
    #[DataProvider('invalidPolicies')]
    public function testInvalidPolicyAndEvidenceValuesAreRejected(string $operation, string $exception): void
    {
        $id = IdentityScenario::id();
        $this->expectException($exception);
        match ($operation) {
            'lifetime' => new SessionLifetime(59),
            'invitation' => new AdminInvitationInput($id, ''),
            'approval' => new AdminApprovalInput($id, ''),
            'actor action' => new ActorChangeInput($id, Realm::USER, 'delete'),
            default => SystemAdmin::invite(IdentityId::create(), IdentityId::create(), '', new DateTimeImmutable()),
        };
    }

    /** @return iterable<string, array{string, class-string<Throwable>}> */
    public static function invalidPolicies(): iterable
    {
        yield 'lifetime' => ['lifetime', InvalidArgumentException::class];
        yield 'invitation' => ['invitation', InvalidUserInput::class];
        yield 'approval' => ['approval', InvalidUserInput::class];
        yield 'actor action' => ['actor action', InvalidUserInput::class];
        yield 'domain evidence' => ['evidence', InvalidUserInput::class];
    }

    public function testApprovalRequiresSeparateEvidence(): void
    {
        $admin = SystemAdmin::invite(IdentityId::create(), IdentityId::create(), 'ticket:123', new DateTimeImmutable());
        $this->expectException(InvalidUserInput::class);
        $admin->approve('ticket:123');
    }

    public function testQueriesSerializeOnlySafeIdentitySelectors(): void
    {
        $id        = IdentityScenario::id();
        $principal = new Principal(Realm::USER, ['accountId' => $id, 'actorId' => $id, 'sid' => $id, 'jti' => $id, 'authVersion' => 1]);
        self::assertSame([], new GetCurrentIdentity($principal)->jsonSerialize());
        self::assertSame(['operationId' => $id], new GetIdentityOperation($id)->jsonSerialize());
        self::assertSame(['actorId' => $id, 'realm' => 'USER'], new GetActorHistory($principal, Realm::USER, $id)->jsonSerialize());
        $this->expectException(AuthenticationDenied::class);
        $principal->assertAdmin();
    }
}
