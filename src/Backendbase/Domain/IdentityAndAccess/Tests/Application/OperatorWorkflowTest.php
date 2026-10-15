<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\Operations\OperatorWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\OperatorInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\IdentityConflict;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Shared\Exception\InvalidUserInput;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OperatorWorkflowTest extends TestCase
{
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
            self::assertSame(1, $fixture->connection()->fetchOne('SELECT COUNT(*) FROM identity_operator_action'));
        }

        $approval = new OperatorInput('approve-first-admin', $actorId, 'operator', '', 'ticket:approve');
        $workflow->run($approval, $now, false);
        $workflow->run($approval, $now, true);
        $principal = IdentityScenario::principal($fixture, Realm::ADMIN, 'ada@example.com');
        self::assertSame($actorId, $principal->identity()['actorId']);
        $this->expectException(IdentityConflict::class);
        $workflow->run($invitation, $now, true);
    }
}
