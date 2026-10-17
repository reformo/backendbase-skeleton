<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Persistence\State;

use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\AccountState;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\ActorState;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\ContactState;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class IdentityStateMappingTest extends TestCase
{
    #[DataProvider('records')]
    public function testTypedReplacementIsPersistedAndEarlierSnapshotsRemainUnchanged(string $kind, string $table): void
    {
        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $manager   = $container->get(EntityManagerInterface::class);
        $record    = StateExamples::record($kind);
        $manager->persist($record);
        $manager->flush();
        $id    = $record->id();
        $class = $record::class;
        $manager->clear();
        $loaded = $manager->find($class, $id);
        self::assertNotNull($loaded);
        $before  = $loaded->state();
        $values  = $before->toArray();
        $changed = self::changed($before);
        $loaded->replaceState($changed);
        $manager->flush();
        $manager->clear();
        self::assertSame($values, $before->toArray());
        $connection = $fixture->connection();
        $stored     = $connection->fetchOne('SELECT state FROM ' . $table);
        self::assertIsString($stored);
        self::assertSame($changed->toArray(), json_decode($stored, true, 16, JSON_THROW_ON_ERROR));
        $reloaded = $manager->find($class, $id);
        self::assertNotNull($reloaded);
        self::assertEquals($changed, $reloaded->state());
        self::assertNotSame($changed, $reloaded->state());
    }

    #[DataProvider('records')]
    public function testHydratedUnknownFieldsFailBeforeDomainUse(string $kind, string $table): void
    {
        $fixture   = IdentityFixture::create();
        $container = $fixture->container();
        $manager   = $container->get(EntityManagerInterface::class);
        $record    = StateExamples::record($kind);
        $manager->persist($record);
        $manager->flush();
        $id         = $record->id();
        $class      = $record::class;
        $state      = $record->state();
        $connection = $fixture->connection();
        $connection->executeStatement('UPDATE ' . $table . ' SET state = :state', ['state' => json_encode([...$state->toArray(), 'futureField' => true], JSON_THROW_ON_ERROR)]);
        $manager->clear();
        $loaded = $manager->find($class, $id);
        self::assertNotNull($loaded);
        $this->expectException(AuthenticationUnavailable::class);
        $loaded->toDomain();
    }

    /** @return iterable<string, array{string, string}> */
    public static function records(): iterable
    {
        yield 'Account' => ['account', 'identity_account'];
        yield 'User' => ['user', 'identity_user'];
        yield 'Admin' => ['admin', 'identity_admin'];
        yield 'Contact' => ['contact', 'contact_info'];
    }

    private static function changed(AccountState|ActorState|ContactState $state): AccountState|ActorState|ContactState
    {
        $values = $state->toArray();

        return match (true) {
            $state instanceof AccountState => AccountState::fromArray([...$values, 'ownershipVerified' => true, 'authVersion' => 2]),
            $state instanceof ActorState => ActorState::fromArray([...$values, 'approved' => true, 'invitation' => 'invitation', 'approval' => 'approval']),
            default => ContactState::fromArray([...$values, 'generationId' => 'generation', 'codeHash' => 'hash', 'failedAttempts' => 1, 'consumedAt' => '2026-10-17 10:01:00.000000']),
        };
    }
}
