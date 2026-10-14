<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\Workflows\EnrollmentWorkflow;
use Backendbase\Domain\IdentityAndAccess\Application\Workflows\LoginWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\LoginInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\ProofInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\OwnershipNotifier;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\CapturedOwnershipNotifier;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnrollmentFailureTest extends TestCase
{
    #[Test]
    public function itRejectsSessionAndEnrollmentBeforeOwnershipProof(): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::registered($fixture);
        $container  = $fixture->container();
        $enrollment = $container->get(EnrollmentWorkflow::class);
        $login      = $container->get(LoginWorkflow::class);
        foreach ([$enrollment->prepare(...), $login->begin(...)] as $attempt) {
            try {
                $attempt(IdentityScenario::id(), Realm::USER, new LoginInput('ada.user', 'valid-password-123'));
                self::fail('An unverified Account cannot authenticate.');
            } catch (AuthenticationDenied) {
                self::addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function itKeepsFailureCountsAfterRejectedOwnershipProofAndStopsAfterFiveAttempts(): void
    {
        $fixture       = IdentityFixture::create();
        $registered    = IdentityScenario::registered($fixture);
        $container     = $fixture->container();
        $enrollment    = $container->get(EnrollmentWorkflow::class);
        $notifications = $container->get(OwnershipNotifier::class);
        self::assertInstanceOf(CapturedOwnershipNotifier::class, $notifications);
        $valid   = $notifications->code($registered['challengeId']);
        $invalid = $valid === '000000' ? '000001' : '000000';
        foreach ([$invalid, $invalid, $invalid, $invalid, $invalid, $valid] as $code) {
            try {
                $enrollment->verifyOwnership(Realm::USER, new ProofInput($registered['challengeId'], $code));
                self::fail('An exhausted challenge must reject every proof.');
            } catch (AuthenticationDenied) {
                self::addToAssertionCount(1);
            }
        }

        $store = $container->get(IdentityStore::class);
        $state = $store->transaction(static function () use ($store, $registered): array {
            $challenge = $store->challenge($registered['challengeId']);

            return $challenge->snapshot();
        });
        self::assertSame(5, $state['attempts']);
        self::assertFalse($state['consumed']);
    }

    #[Test]
    public function itExpiresChallengesAndInvalidatesPreviousProofOnResend(): void
    {
        $fixture       = IdentityFixture::create();
        $registered    = IdentityScenario::registered($fixture);
        $container     = $fixture->container();
        $enrollment    = $container->get(EnrollmentWorkflow::class);
        $notifications = $container->get(OwnershipNotifier::class);
        self::assertInstanceOf(CapturedOwnershipNotifier::class, $notifications);
        $oldCode     = $notifications->code($registered['challengeId']);
        $replacement = $enrollment->resendOwnership(IdentityScenario::id(), Realm::USER, new LoginInput('ada.user', 'valid-password-123'));
        try {
            $enrollment->verifyOwnership(Realm::USER, new ProofInput($registered['challengeId'], $oldCode));
            self::fail('Resend must invalidate the previous challenge.');
        } catch (AuthenticationDenied) {
            self::addToAssertionCount(1);
        }

        IdentityScenario::advance($fixture, 'PT300S');
        $this->expectException(AuthenticationDenied::class);
        $enrollment->verifyOwnership(Realm::USER, new ProofInput($replacement['challengeId'], $notifications->code($replacement['challengeId'])));
    }
}
