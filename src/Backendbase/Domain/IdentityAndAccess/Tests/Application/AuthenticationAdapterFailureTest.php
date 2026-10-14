<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationGate;
use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationTokens;
use Backendbase\Domain\IdentityAndAccess\Application\Credentials;
use Backendbase\Domain\IdentityAndAccess\Application\Workflows\AdministrationWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\Administration\AdminApprovalInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenCodec;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Actor;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\LoginName;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Password;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\SystemAdmin;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\User;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Shared\Exception\ResourceAccessForbidden;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AuthenticationAdapterFailureTest extends TestCase
{
    public function testInvalidPasswordAndReusedFactorStepsAreRejectedAtTheCredentialBoundary(): void
    {
        $fixture     = IdentityFixture::create();
        $registered  = IdentityScenario::registered($fixture);
        $container   = $fixture->container();
        $store       = $container->get(IdentityStore::class);
        $credentials = $container->get(Credentials::class);
        try {
            $store->transaction(static fn () => $credentials->assertPassword($registered['accountId'], new Password('wrong-password-123')));
            self::fail('The credential boundary must reject an incorrect password.');
        } catch (AuthenticationDenied $exception) {
            self::assertSame('Authentication is not permitted.', $exception->getMessage());
        }

        $this->expectException(AuthenticationDenied::class);
        $store->transaction(static fn () => $credentials->consumeFactor($registered['accountId'], -1, false));
    }

    public function testAnUnrecognizedActorTypeCannotEnterTheProductionRepository(): void
    {
        $fixture    = IdentityFixture::create();
        $registered = IdentityScenario::registered($fixture);
        $container  = $fixture->container();
        $store      = $container->get(IdentityStore::class);
        $id         = IdentityId::create();
        $admin      = SystemAdmin::invite($id, IdentityId::fromString($registered['accountId']), new LoginName('test.admin'), 'invitation:123', new DateTimeImmutable());
        $actor      = new class ($id, $admin->snapshot()) extends Actor {
            public function realm(): Realm
            {
                return Realm::ADMIN;
            }
        };
        $this->expectException(AuthenticationDenied::class);
        $store->transaction(static fn () => $store->saveActor($actor));
    }

    public function testAnIncorrectAdapterActorTypeCannotReceiveAdminApproval(): void
    {
        $now       = new DateTimeImmutable('2026-10-14T10:00:00Z');
        $accountId = IdentityId::create();
        $account   = Account::create($accountId, new AccountEmail('admin@example.com'), $now);
        $account->verifyOwnership();
        $admin = SystemAdmin::invite(IdentityId::create(), $accountId, new LoginName('valid.admin'), 'invitation:123', $now);
        $admin->approve('approval:123');
        $target = User::create(IdentityId::create(), $accountId, new LoginName('target.user'), $now);
        $store  = $this->createStub(IdentityStore::class);
        $store->method('transaction')->willReturnCallback(static fn (callable $work): mixed => $work());
        $store->method('actorReference')->willReturn($target);
        $store->method('account')->willReturn($account);
        $store->method('actor')->willReturn($admin);
        $store->method('actorForUpdate')->willReturn($target);
        $tokens    = new AuthenticationTokens($this->createStub(TokenCodec::class), $this->createStub(TokenRegistry::class));
        $gate      = new AuthenticationGate($store, $tokens);
        $principal = new Principal(Realm::ADMIN, ['accountId' => $account->id(), 'actorId' => $admin->id(), 'sid' => IdentityScenario::id(), 'jti' => IdentityScenario::id(), 'authVersion' => 1]);
        $this->expectException(ResourceAccessForbidden::class);
        new AdministrationWorkflow($store, $gate)->approve($principal, new AdminApprovalInput($target->id(), 'approval:456'));
    }
}
