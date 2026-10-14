<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationGate;
use Backendbase\Domain\IdentityAndAccess\Application\Credentials;
use Backendbase\Domain\IdentityAndAccess\Application\Workflows\EnrollmentWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\LoginInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\ProofInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Challenges\Challenge;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class IdentityBoundaryStateTest extends TestCase
{
    public function testAChallengeCannotReferToAnActorOwnedByAnotherAccount(): void
    {
        $fixture   = IdentityFixture::create();
        $first     = IdentityScenario::registered($fixture);
        $second    = IdentityScenario::registered($fixture, 'second.user', 'second@example.com');
        $container = $fixture->container();
        $store     = $container->get(IdentityStore::class);
        $this->expectException(AuthenticationDenied::class);
        $store->transaction(static function () use ($store, $first, $second): void {
            $original         = $store->challenge($first['challengeId']);
            $state            = $original->snapshot();
            $state['actorId'] = $second['actorId'];
            $store->saveChallenge(new Challenge(IdentityId::create(), $state));
        });
    }

    public function testCurrentSessionRejectsPersistedActorAccountMismatch(): void
    {
        $fixture    = IdentityFixture::create();
        $first      = IdentityScenario::enrolled($fixture);
        $principal  = IdentityScenario::principal($fixture, $first['secret']);
        $connection = $fixture->connection();
        $accountId  = Uuid::uuid7();
        $connection->executeStatement('UPDATE identity_user SET account_id = :account WHERE uuid = :actor', ['account' => $accountId->getBytes(), 'actor' => $first['actorId']], ['account' => ParameterType::BINARY]);
        $container = $fixture->container();
        $gate      = $container->get(AuthenticationGate::class);
        $this->expectException(AuthenticationDenied::class);
        $gate->confirm($principal);
    }

    public function testChallengesCannotCrossRealmsAndVerifiedOwnershipCannotBeResent(): void
    {
        $fixture    = IdentityFixture::create();
        $registered = IdentityScenario::registered($fixture);
        $container  = $fixture->container();
        $workflow   = $container->get(EnrollmentWorkflow::class);
        try {
            $workflow->verifyOwnership(Realm::ADMIN, new ProofInput($registered['challengeId'], '123456'));
            self::fail('The challenge realm must match the trusted route.');
        } catch (AuthenticationDenied $exception) {
            self::assertSame('Authentication is not permitted.', $exception->getMessage());
        }

        $enrolled = IdentityScenario::enrolled($fixture, 'second.user', 'second@example.com');
        self::assertNotEmpty($enrolled['secret']);
        $this->expectException(AuthenticationDenied::class);
        $workflow->resendOwnership(IdentityScenario::id(), Realm::USER, new LoginInput('second.user', 'valid-password-123'));
    }

    public function testReplacingPendingEnrollmentInvalidatesTheEarlierChallenge(): void
    {
        $fixture = IdentityFixture::create();
        IdentityScenario::enrolled($fixture);
        $container   = $fixture->container();
        $store       = $container->get(IdentityStore::class);
        $credentials = $container->get(Credentials::class);
        $registered  = IdentityScenario::registered($fixture, 'pending.user', 'pending@example.com');
        $store->transaction(static function () use ($store, $credentials, $registered): void {
            $account = $store->accountForUpdate($registered['accountId']);
            $account->verifyOwnership();
            $store->saveAccount($account);
            self::assertNull($credentials->factorStep($account->id(), '123456', false, 'binding', new DateTimeImmutable('2026-10-14T10:00:00Z')));
        });
        $workflow = $container->get(EnrollmentWorkflow::class);
        $input    = new LoginInput('pending.user', 'valid-password-123');
        $first    = $workflow->prepare(IdentityScenario::id(), Realm::USER, $input);
        $workflow->prepare(IdentityScenario::id(), Realm::USER, $input);
        $this->expectException(AuthenticationDenied::class);
        $workflow->complete(Realm::USER, new ProofInput($first['challengeId'], IdentityScenario::code($fixture, $first['totpSecret'])));
    }
}
