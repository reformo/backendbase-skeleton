<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationGate;
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
        $first      = IdentityScenario::verified($fixture);
        $principal  = IdentityScenario::principal($fixture);
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

        $enrolled = IdentityScenario::verified($fixture, 'second.user', 'second@example.com');
        self::assertNotEmpty($enrolled['accountId']);
        $this->expectException(AuthenticationDenied::class);
        $workflow->resendOwnership(IdentityScenario::id(), Realm::USER, new LoginInput('second.user', 'valid-password-123'));
    }
}
