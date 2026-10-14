<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Support;

use Backendbase\Domain\IdentityAndAccess\Application\Workflows\EnrollmentWorkflow;
use Backendbase\Domain\IdentityAndAccess\Application\Workflows\LoginWorkflow;
use Backendbase\Domain\IdentityAndAccess\Application\Workflows\RegistrationWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\LoginInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\ProofInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\RegistrationInput;
use Backendbase\Domain\IdentityAndAccess\Contracts\OwnershipNotifier;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\LoginName;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\SystemAdmin;
use Backendbase\Shared\Time\Clock;
use DateInterval;
use PHPUnit\Framework\Assert;
use Tests\Support\Time\FrozenClock;

final class IdentityScenario
{
    public static function id(): string
    {
        $id = IdentityId::create();

        return $id->toString();
    }

    /** @return array{accountId: string, actorId: string, challengeId: string, expiresIn: int} */
    public static function registered(IdentityFixture $fixture, string $name = 'ada.user', string $email = 'ada@example.com'): array
    {
        $container    = $fixture->container();
        $registration = $container->get(RegistrationWorkflow::class);

        return $registration->register(self::id(), new RegistrationInput($email, $name, 'valid-password-123'));
    }

    /** @return array{accountId: string, actorId: string} */
    public static function verified(IdentityFixture $fixture, string $name = 'ada.user', string $email = 'ada@example.com'): array
    {
        $registered    = self::registered($fixture, $name, $email);
        $container     = $fixture->container();
        $notifications = $container->get(OwnershipNotifier::class);
        Assert::assertInstanceOf(CapturedOwnershipNotifier::class, $notifications);
        $enrollment = $container->get(EnrollmentWorkflow::class);
        $enrollment->verifyOwnership(Realm::USER, new ProofInput($registered['challengeId'], $notifications->code($registered['challengeId'])));

        return ['accountId' => $registered['accountId'], 'actorId' => $registered['actorId']];
    }

    /** @return array{accountId: string, actorId: string, userActorId: string} */
    public static function admin(IdentityFixture $fixture, string $name = 'ada.admin', string $email = 'admin@example.com'): array
    {
        $enrolled  = self::verified($fixture, $name, $email);
        $container = $fixture->container();
        $store     = $container->get(IdentityStore::class);
        $clock     = $container->get(Clock::class);
        $adminId   = IdentityId::create();
        $store->transaction(static function () use ($store, $clock, $enrolled, $adminId, $name): void {
            $admin = SystemAdmin::invite($adminId, IdentityId::fromString($enrolled['accountId']), new LoginName($name), 'fixture:invitation', $clock->now());
            $admin->approve('fixture:approval');
            $store->saveActor($admin);
        });

        return ['accountId' => $enrolled['accountId'], 'actorId' => $adminId->toString(), 'userActorId' => $enrolled['actorId']];
    }

    public static function advance(IdentityFixture $fixture, string $interval): void
    {
        $container = $fixture->container();
        $clock     = $container->get(Clock::class);
        Assert::assertInstanceOf(FrozenClock::class, $clock);
        $clock->advance(new DateInterval($interval));
    }

    public static function principal(IdentityFixture $fixture, Realm $realm = Realm::USER, string $name = 'ada.user'): Principal
    {
        $container   = $fixture->container();
        $login       = $container->get(LoginWorkflow::class);
        [$principal] = $login->authenticate($realm, new LoginInput($name, 'valid-password-123'));

        return $principal;
    }
}
