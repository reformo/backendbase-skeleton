<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Support;

use Backendbase\Domain\IdentityAndAccess\Application\Workflows\LoginWorkflow;
use Backendbase\Domain\IdentityAndAccess\Application\Workflows\RegistrationWorkflow;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\Input\LoginInput;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
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

    /** @return array{accountId: string, actorId: string} */
    public static function registered(IdentityFixture $fixture, string $mobile = '+905551234567', string $email = 'ada@example.com'): array
    {
        $container    = $fixture->container();
        $registration = $container->get(RegistrationWorkflow::class);

        return $registration->register(self::id(), ContactScenario::input($fixture, $email, $mobile));
    }

    /** @return array{accountId: string, actorId: string} */
    public static function verified(IdentityFixture $fixture, string $mobile = '+905551234567', string $email = 'ada@example.com'): array
    {
        return self::registered($fixture, $mobile, $email);
    }

    /** @return array{accountId: string, actorId: string, userActorId: string} */
    public static function admin(IdentityFixture $fixture, string $mobile = '+905551234568', string $email = 'admin@example.com'): array
    {
        $enrolled  = self::verified($fixture, $mobile, $email);
        $container = $fixture->container();
        $store     = $container->get(IdentityStore::class);
        $clock     = $container->get(Clock::class);
        $adminId   = IdentityId::create();
        $store->transaction(static function () use ($store, $clock, $enrolled, $adminId): void {
            $admin = SystemAdmin::invite($adminId, IdentityId::fromString($enrolled['accountId']), 'fixture:invitation', $clock->now());
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

    public static function principal(IdentityFixture $fixture, Realm $realm = Realm::USER, string $identifier = 'ada@example.com'): Principal
    {
        $container   = $fixture->container();
        $login       = $container->get(LoginWorkflow::class);
        [$principal] = $login->authenticate($realm, new LoginInput($identifier, 'valid-password-123'));

        return $principal;
    }
}
