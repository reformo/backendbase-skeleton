<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\Operations\OperatorWorkflow;
use Backendbase\Domain\IdentityAndAccess\Application\Workflows\EnrollmentWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\OperatorInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\ProofInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\OwnershipNotifier;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\CapturedOwnershipNotifier;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Shared\Exception\InvalidUserInput;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OperatorWorkflowTest extends TestCase
{
    public function testFirstAdminNeedsVerifiedOwnershipAndSeparateEvidenceBeforeLogin(): void
    {
        $fixture    = IdentityFixture::create();
        $registered = IdentityScenario::registered($fixture);
        $container  = $fixture->container();
        $workflow   = $container->get(OperatorWorkflow::class);
        $now        = new DateTimeImmutable('2026-10-14T10:00:00Z');
        $invitation = new OperatorInput('invite-first-admin', $registered['accountId'], 'operator', 'ticket:invite', '', 'first.admin');
        try {
            $workflow->run($invitation, $now, true);
            self::fail('Ownership is required.');
        } catch (AuthenticationDenied) {
            self::assertSame(0, $fixture->connection()->fetchOne('SELECT COUNT(*) FROM identity_admin'));
        }

        $notifier = $container->get(OwnershipNotifier::class);
        self::assertInstanceOf(CapturedOwnershipNotifier::class, $notifier);
        $enrollment = $container->get(EnrollmentWorkflow::class);
        $enrollment->verifyOwnership(Realm::USER, new ProofInput($registered['challengeId'], $notifier->code($registered['challengeId'])));
        $workflow->run($invitation, $now, false);
        self::assertSame(0, $fixture->connection()->fetchOne('SELECT COUNT(*) FROM identity_admin'));
        $actorId      = $workflow->run($invitation, $now, true);
        $sameEvidence = new OperatorInput('approve-first-admin', $actorId, 'operator', '', 'ticket:invite');
        try {
            $workflow->run($sameEvidence, $now, true);
            self::fail('Invitation and approval references must differ.');
        } catch (InvalidUserInput) {
            self::assertSame(1, $fixture->connection()->fetchOne('SELECT COUNT(*) FROM identity_operator_action'));
        }

        $approval = new OperatorInput('approve-first-admin', $actorId, 'operator', '', 'ticket:approve');
        $workflow->run($approval, $now, false);
        $workflow->run($approval, $now, true);
        $principal = IdentityScenario::principal($fixture, Realm::ADMIN, 'first.admin');
        self::assertSame($actorId, $principal->identity()['actorId']);
        $this->expectException(IdentityConflict::class);
        $workflow->run($invitation, $now, true);
    }
}
