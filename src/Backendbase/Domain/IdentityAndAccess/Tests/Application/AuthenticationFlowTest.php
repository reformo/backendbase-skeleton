<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuthenticationFlowTest extends TestCase
{
    #[Test]
    public function itUsesPasswordAuthenticationForARevocableVerifiedUserSession(): void
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
        $loginId = IdentityId::create();
        $login   = new Input\RealmLogin(Realm::USER, new Input\LoginInput('ADA.USER', 'valid-password-123'));
        $commands->handle(new Authentication\Login($loginId->toString(), $login));
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
