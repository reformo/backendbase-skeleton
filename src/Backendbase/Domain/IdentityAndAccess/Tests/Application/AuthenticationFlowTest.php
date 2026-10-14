<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Totp;
use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationGate;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Authentication;
use Backendbase\Domain\IdentityAndAccess\Contracts\Command\Lifecycle\Logout;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input;
use Backendbase\Domain\IdentityAndAccess\Contracts\OwnershipNotifier;
use Backendbase\Domain\IdentityAndAccess\Contracts\Query\GetIdentityOperation;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\CapturedOwnershipNotifier;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\Time\Clock;
use DateInterval;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\Time\FrozenClock;

use function intdiv;

final class AuthenticationFlowTest extends TestCase
{
    #[Test]
    public function itRequiresOwnershipAndTotpBeforeARevocableUserSession(): void
    {
        $fixture        = IdentityFixture::create();
        $container      = $fixture->container();
        $commands       = $container->get(CommandBus::class);
        $queries        = $container->get(QueryBus::class);
        $id             = IdentityId::create();
        $registrationId = $id->toString();
        $registration   = new Authentication\RegisterUser($registrationId, new Input\RegistrationInput('ADA@EXAMPLE.COM', 'Ada.User', 'valid-password-123'));
        self::assertSame(['operationId' => $registrationId], $registration->jsonSerialize());
        $commands->handle($registration);
        $result = $queries->handle(new GetIdentityOperation($registrationId));
        self::assertSame(201, $result->status());
        $data = $result->data();
        self::assertArrayHasKey('accountId', $data);
        self::assertArrayNotHasKey('accessToken', $data);
        $ownership = $container->get(OwnershipNotifier::class);
        self::assertInstanceOf(CapturedOwnershipNotifier::class, $ownership);
        $proof    = new Input\RealmProof(Realm::USER, new Input\ProofInput($registrationId, $ownership->code($registrationId)));
        $verifyId = IdentityId::create();
        $commands->handle(new Authentication\VerifyOwnership($verifyId->toString(), $proof));
        $enrollmentId = IdentityId::create();
        $login        = new Input\RealmLogin(Realm::USER, new Input\LoginInput('ADA.USER', 'valid-password-123'));
        $commands->handle(new Authentication\PrepareTotpEnrollment($enrollmentId->toString(), $login));
        $result     = $queries->handle(new GetIdentityOperation($enrollmentId->toString()));
        $enrollment = $result->data();
        self::assertIsString($enrollment['totpSecret']);
        $secret = $enrollment['totpSecret'];
        $clock  = $container->get(Clock::class);
        self::assertInstanceOf(FrozenClock::class, $clock);
        $now        = $clock->now();
        $code       = Totp::code($secret, intdiv($now->getTimestamp(), 30));
        $completeId = IdentityId::create();
        $commands->handle(new Authentication\CompleteTotpEnrollment($completeId->toString(), new Input\RealmProof(Realm::USER, new Input\ProofInput($enrollmentId->toString(), $code))));
        $clock->advance(new DateInterval('PT31S'));
        $beginId = IdentityId::create();
        $commands->handle(new Authentication\BeginLogin($beginId->toString(), $login));
        $loginId = IdentityId::create();
        $now     = $clock->now();
        $code    = Totp::code($secret, intdiv($now->getTimestamp(), 30));
        $commands->handle(new Authentication\CompleteLogin($loginId->toString(), new Input\RealmProof(Realm::USER, new Input\ProofInput($beginId->toString(), $code))));
        $result  = $queries->handle(new GetIdentityOperation($loginId->toString()));
        $session = $result->data();
        self::assertIsString($session['accessToken']);
        $gate      = $container->get(AuthenticationGate::class);
        $principal = $gate->verify(Realm::USER, $session['accessToken']);
        self::assertSame(Realm::USER, $principal->realm());
        $logoutId = IdentityId::create();
        $commands->handle(new Logout($logoutId->toString(), $principal));
        $this->expectException(AuthenticationDenied::class);
        $gate->verify(Realm::USER, $session['accessToken']);
    }
}
