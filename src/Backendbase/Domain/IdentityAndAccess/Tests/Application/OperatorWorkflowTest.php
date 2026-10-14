<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\SodiumAuthenticationSecrets;
use Backendbase\Domain\IdentityAndAccess\Application\Operations\OperatorWorkflow;
use Backendbase\Domain\IdentityAndAccess\Application\Workflows\EnrollmentWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\AuthenticationSecrets;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\LoginInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\OperatorInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\ProofInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\OperatorStore;
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

use function base64_encode;
use function str_repeat;

final class OperatorWorkflowTest extends TestCase
{
    public function testFirstAdminNeedsVerifiedOwnershipSeparateEvidenceAndMfaBeforeLogin(): void
    {
        $fixture    = IdentityFixture::create();
        $registered = IdentityScenario::registered($fixture);
        $container  = $fixture->container();
        $workflow   = $container->get(OperatorWorkflow::class);
        $secrets    = $container->get(AuthenticationSecrets::class);
        $now        = new DateTimeImmutable('2026-10-14T10:00:00Z');
        $invitation = new OperatorInput('invite-first-admin', $registered['accountId'], 'operator', 'ticket:invite', '', 'first.admin');
        try {
            $workflow->run($invitation, $secrets, $now, true);
            self::fail('Ownership is required.');
        } catch (AuthenticationDenied) {
            self::assertSame(0, $fixture->connection()->fetchOne('SELECT COUNT(*) FROM identity_admin'));
        }

        $notifier = $container->get(OwnershipNotifier::class);
        self::assertInstanceOf(CapturedOwnershipNotifier::class, $notifier);
        $enrollment = $container->get(EnrollmentWorkflow::class);
        $enrollment->verifyOwnership(Realm::USER, new ProofInput($registered['challengeId'], $notifier->code($registered['challengeId'])));
        $workflow->run($invitation, $secrets, $now, false);
        self::assertSame(0, $fixture->connection()->fetchOne('SELECT COUNT(*) FROM identity_admin'));
        $actorId      = $workflow->run($invitation, $secrets, $now, true);
        $sameEvidence = new OperatorInput('approve-first-admin', $actorId, 'operator', '', 'ticket:invite');
        try {
            $workflow->run($sameEvidence, $secrets, $now, true);
            self::fail('Invitation and approval references must differ.');
        } catch (InvalidUserInput) {
            self::assertSame(1, $fixture->connection()->fetchOne('SELECT COUNT(*) FROM identity_operator_action'));
        }

        $approval = new OperatorInput('approve-first-admin', $actorId, 'operator', '', 'ticket:approve');
        $workflow->run($approval, $secrets, $now, false);
        $workflow->run($approval, $secrets, $now, true);
        $prepared = $enrollment->prepare(IdentityScenario::id(), Realm::ADMIN, new LoginInput('first.admin', 'valid-password-123'));
        $enrollment->complete(Realm::ADMIN, new ProofInput($prepared['challengeId'], IdentityScenario::code($fixture, $prepared['totpSecret'])));
        IdentityScenario::advance($fixture, 'PT31S');
        $principal = IdentityScenario::principal($fixture, $prepared['totpSecret'], Realm::ADMIN, 'first.admin');
        self::assertSame($actorId, $principal->identity()['actorId']);
        $this->expectException(IdentityConflict::class);
        $workflow->run($invitation, $secrets, $now, true);
    }

    public function testRecoveryInvalidatesSessionsAndRequiresOwnershipAndNewFactor(): void
    {
        $fixture   = IdentityFixture::create();
        $enrolled  = IdentityScenario::enrolled($fixture);
        $principal = IdentityScenario::principal($fixture, $enrolled['secret']);
        $container = $fixture->container();
        $workflow  = $container->get(OperatorWorkflow::class);
        $secrets   = $container->get(AuthenticationSecrets::class);
        $input     = new OperatorInput('recover-totp', $enrolled['accountId'], 'operator', 'request:123', 'approval:456');
        $now       = new DateTimeImmutable('2026-10-14T10:00:00Z');
        $workflow->run($input, $secrets, $now, false);
        $workflow->run($input, $secrets, $now, true);
        $store      = $container->get(IdentityStore::class);
        $operations = $container->get(OperatorStore::class);
        $store->transaction(static function () use ($store, $operations, $enrolled, $principal): void {
            $account = $store->account($enrolled['accountId']);
            self::assertFalse($account->snapshot()['owned']);
            self::assertNull($operations->read($enrolled['accountId'])['totpSecret']);
            try {
                $account->assertSessionAllowed($principal->identity()['authVersion']);
                self::fail('The old session must fail its database gate.');
            } catch (AuthenticationDenied $exception) {
                self::assertSame('Authentication is not permitted.', $exception->getMessage());
            }
        });
        $enrollment = $container->get(EnrollmentWorkflow::class);
        $proof      = $enrollment->resendOwnership(IdentityScenario::id(), Realm::USER, new LoginInput('ada.user', 'valid-password-123'));
        $notifier   = $container->get(OwnershipNotifier::class);
        self::assertInstanceOf(CapturedOwnershipNotifier::class, $notifier);
        $enrollment->verifyOwnership(Realm::USER, new ProofInput($proof['challengeId'], $notifier->code($proof['challengeId'])));
        $prepared = $enrollment->prepare(IdentityScenario::id(), Realm::USER, new LoginInput('ada.user', 'valid-password-123'));
        self::assertNotSame($enrolled['secret'], $prepared['totpSecret']);
        $enrollment->complete(Realm::USER, new ProofInput($prepared['challengeId'], IdentityScenario::code($fixture, $prepared['totpSecret'])));
    }

    public function testRewrapPreservesFactorsAndReplayStepsIncludingHistoricalAccounts(): void
    {
        $fixture    = IdentityFixture::create();
        $enrolled   = IdentityScenario::enrolled($fixture);
        $container  = $fixture->container();
        $store      = $container->get(IdentityStore::class);
        $operations = $container->get(OperatorStore::class);
        $workflow   = $container->get(OperatorWorkflow::class);
        $now        = new DateTimeImmutable('2026-10-14T10:00:00Z');
        $store->transaction(static function () use ($store, $enrolled, $now): void {
            $account = $store->accountForUpdate($enrolled['accountId']);
            $account->close($now);
            $store->saveAccount($account);
        });
        $newSecrets = new SodiumAuthenticationSecrets(IdentityFixture::settings(['encryptionKeyId' => 'new-factor', 'encryptionKey' => base64_encode(str_repeat('n', 32)), 'previousEncryptionKeyId' => 'factor-key', 'previousEncryptionKey' => base64_encode(str_repeat('e', 32))]));
        $input      = new OperatorInput('rewrap-totp', $enrolled['accountId'], 'operator', '', 'rotation:123');
        $workflow->run($input, $newSecrets, $now, false);
        $workflow->run($input, $newSecrets, $now, true);
        $credentials = $store->transaction(static fn () => $operations->read($enrolled['accountId']));
        self::assertNotNull($credentials['totpSecret']);
        self::assertStringStartsWith('new-factor:', $credentials['totpSecret']);
        self::assertSame($enrolled['secret'], $newSecrets->decrypt($credentials['totpSecret']));
        self::assertGreaterThan(0, $credentials['lastTotpStep']);
        self::assertNull($credentials['pendingTotpSecret']);
    }
}
