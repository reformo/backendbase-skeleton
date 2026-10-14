<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\Workflows\EnrollmentWorkflow;
use Backendbase\Domain\IdentityAndAccess\Application\Workflows\LoginWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\CredentialStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\LoginInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\ProofInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MfaFailureTest extends TestCase
{
    #[Test]
    public function itStopsAccountWideMfaGuessingAcrossDifferentChallenges(): void
    {
        $fixture   = IdentityFixture::create();
        $enrolled  = IdentityScenario::enrolled($fixture);
        $container = $fixture->container();
        $login     = $container->get(LoginWorkflow::class);
        $input     = new LoginInput('ada.user', 'valid-password-123');
        $code      = IdentityScenario::code($fixture, $enrolled['secret']);
        $invalid   = $code === '000000' ? '000001' : '000000';
        foreach ([$invalid, $invalid, $invalid, $invalid, $invalid, $code] as $proof) {
            $begin = $login->begin(IdentityScenario::id(), Realm::USER, $input);
            try {
                $login->complete(Realm::USER, new ProofInput($begin['challengeId'], $proof));
                self::fail('The shared attempt budget must reject the proof.');
            } catch (AuthenticationDenied) {
                self::addToAssertionCount(1);
            }
        }

        $store       = $container->get(IdentityStore::class);
        $credentials = $container->get(CredentialStore::class);
        $state       = $store->transaction(static fn (): array => $credentials->read($enrolled['accountId']));
        self::assertSame(5, $state['failedMfaAttempts']);
        self::assertNotNull($state['blockedUntil']);
        IdentityScenario::advance($fixture, 'PT300S');
        $principal = IdentityScenario::principal($fixture, $enrolled['secret']);
        self::assertSame(Realm::USER, $principal->realm());
    }

    #[Test]
    public function itRejectsReusingTheAcceptedTotpStepForAnotherLogin(): void
    {
        $fixture  = IdentityFixture::create();
        $enrolled = IdentityScenario::enrolled($fixture);
        IdentityScenario::principal($fixture, $enrolled['secret']);
        $this->expectException(AuthenticationDenied::class);
        IdentityScenario::principal($fixture, $enrolled['secret']);
    }

    #[Test]
    public function itDoesNotReplaceAnActiveFactorThroughEnrollment(): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::enrolled($fixture);
        $container  = $fixture->container();
        $enrollment = $container->get(EnrollmentWorkflow::class);
        $this->expectException(AuthenticationDenied::class);
        $enrollment->prepare(IdentityScenario::id(), Realm::USER, new LoginInput('ada.user', 'valid-password-123'));
    }
}
