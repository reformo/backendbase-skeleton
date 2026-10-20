<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\Operations\OperatorWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\OperatorInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\OperatorStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountMobile;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\SystemAdmin;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\User;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Shared\Exception\InvalidUserInput;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OperatorWorkflowTest extends TestCase
{
    #[DataProvider('invalidLockedActors')]
    public function testApprovalUsesTheLockedActorState(string $invalidState): void
    {
        $now       = new DateTimeImmutable('2026-10-19T10:00:00Z');
        $accountId = IdentityId::create();
        $actorId   = IdentityId::create();
        $account   = Account::create($accountId, new AccountEmail('admin@example.com'), new AccountMobile('+905551234567'), $now);
        $account->verifyOwnership();
        $reference = SystemAdmin::invite($actorId, $accountId, 'invitation', $now);
        $locked    = SystemAdmin::invite($actorId, $accountId, 'invitation', $now);
        match ($invalidState) {
            'wrong type' => $locked = User::create($actorId, $accountId, $now),
            'approved' => $locked->approve('previous-approval'),
            default => $locked->remove($now),
        };
        $store = $this->createMock(IdentityStore::class);
        $store->method('transaction')->willReturnCallback(static fn (callable $work): mixed => $work());
        $store->method('actorReference')->willReturn($reference);
        $store->method('accountForUpdate')->willReturn($account);
        $store->method('actorForUpdate')->willReturn($locked);
        $store->expects(self::never())->method('saveActor');
        $operations = $this->createStub(OperatorStore::class);
        $operations->method('exclusive')->willReturnCallback(static fn (callable $work): mixed => $work());
        $workflow = new OperatorWorkflow($store, $operations);
        $input    = new OperatorInput('approve-first-admin', $reference->id(), 'operator', '', 'approval');
        $this->expectException(IdentityConflict::class);
        $this->expectExceptionMessage('A pending first-Admin invitation is required.');
        $workflow->run($input, $now, true);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidLockedActors(): iterable
    {
        yield 'wrong type' => ['wrong type'];
        yield 'approved after reference read' => ['approved'];
        yield 'removed after reference read' => ['removed'];
    }

    public function testFirstAdminNeedsSeparateEvidenceBeforeLogin(): void
    {
        $fixture      = IdentityFixture::create();
        $registered   = IdentityScenario::registered($fixture);
        $container    = $fixture->container();
        $workflow     = $container->get(OperatorWorkflow::class);
        $now          = new DateTimeImmutable('2026-10-14T10:00:00Z');
        $invitation   = new OperatorInput('invite-first-admin', $registered['accountId'], 'operator', 'ticket:invite', '');
        $actorId      = $workflow->run($invitation, $now, true);
        $sameEvidence = new OperatorInput('approve-first-admin', $actorId, 'operator', '', 'ticket:invite');
        try {
            $workflow->run($sameEvidence, $now, true);
            self::fail('Invitation and approval references must differ.');
        } catch (InvalidUserInput) {
            $state = $fixture->connection()->fetchOne('SELECT state FROM `admin`');
            self::assertIsString($state);
            self::assertJsonStringEqualsJsonString('{"approved":false,"invitation":"ticket:invite","approval":null}', $state);
        }

        $approval = new OperatorInput('approve-first-admin', $actorId, 'operator', '', 'ticket:approve');
        $workflow->run($approval, $now, false);
        self::assertSame($state, $fixture->connection()->fetchOne('SELECT state FROM `admin`'));
        $workflow->run($approval, $now, true);
        $approved = $fixture->connection()->fetchOne('SELECT state FROM `admin`');
        self::assertJsonStringEqualsJsonString('{"approved":true,"invitation":"ticket:invite","approval":"ticket:approve"}', $approved);
        $principal = IdentityScenario::principal($fixture, Realm::ADMIN, 'ada@example.com');
        self::assertSame($actorId, $principal->identity()['actorId']);
        $this->expectException(IdentityConflict::class);
        $workflow->run($invitation, $now, true);
    }
}
