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
