<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters;

use Backendbase\Domain\IdentityAndAccess\Adapters\ReadModel\DoctrineIdentityReadRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Account;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountMobile;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\StateField;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class PersistedIdentityStateTest extends TestCase
{
    #[DataProvider('ownershipStates')]
    public function testOwnershipVerificationUsesTheExplicitPersistedKey(bool $ownershipVerified): void
    {
        $fixture = IdentityFixture::create();
        $account = Account::create(IdentityId::create(), new AccountEmail('test@example.com'), new AccountMobile('+905551234567'), new DateTimeImmutable('2026-10-17T10:00:00Z'));
        if ($ownershipVerified) {
            $account->verifyOwnership();
        }

        $container = $fixture->container();
        $store     = $container->get(IdentityStore::class);
        $store->transaction(static fn () => $store->saveAccount($account));
        $connection = $fixture->connection();
        $stored     = $connection->fetchOne('SELECT state FROM identity_account');
        self::assertIsString($stored);
        $state = json_decode($stored, true, 16, JSON_THROW_ON_ERROR);
        self::assertIsArray($state);
        self::assertSame($ownershipVerified, $state['ownershipVerified']);
        self::assertArrayNotHasKey('owned', $state);
        $accountId = $account->id();
        $loaded    = $store->transaction(static fn (): Account => $store->account($accountId));
        $snapshot  = $loaded->snapshot();
        self::assertSame($ownershipVerified, $snapshot['ownershipVerified']);
        self::assertArrayNotHasKey('owned', $snapshot);
    }

    /** @return iterable<string, array{bool}> */
    public static function ownershipStates(): iterable
    {
        yield 'ownership unverified' => [false];
        yield 'ownership verified' => [true];
    }

    #[DataProvider('invalidFields')]
    public function testPersistedFieldTypesAreValidatedBeforeDomainReconstitution(string $fieldType): void
    {
        $this->expectException(AuthenticationUnavailable::class);
        match ($fieldType) {
            'string' => StateField::string(['value' => null], 'value'),
            'integer' => StateField::integer(['value' => '1'], 'value'),
            'boolean' => StateField::boolean(['value' => 1], 'value'),
            'missing nullable' => StateField::nullableString([], 'value'),
            default => StateField::nullableString(['value' => []], 'value'),
        };
    }

    /** @return iterable<string, array{string}> */
    public static function invalidFields(): iterable
    {
        yield 'string' => ['string'];
        yield 'integer' => ['integer'];
        yield 'boolean' => ['boolean'];
        yield 'nullable string' => ['nullable-string'];
        yield 'missing nullable' => ['missing nullable'];
    }

    /** @param array<string, mixed> $changes */
    #[DataProvider('invalidHistoryRows')]
    public function testMalformedHistoryRowsCannotBecomePublicReadModels(array $changes): void
    {
        $id         = Uuid::uuid7();
        $row        = ['uuid' => $id->toString(), 'account_id' => $id->getBytes(), 'normalized_email' => 'test@example.com', 'normalized_mobile' => '+905551234567', 'first_name' => null, 'last_name' => null, 'created_at' => '2026-10-14 10:00:00.000000', 'deleted_at' => null, 'state' => '{"approved":true,"invitation":null,"approval":null}'];
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn([...$row, ...$changes]);
        $repository = new DoctrineIdentityReadRepository($connection);
        $this->expectException(AuthenticationUnavailable::class);
        $repository->actorHistory(Realm::USER, $id->toString());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidHistoryRows(): iterable
    {
        yield 'malformed JSON' => [['state' => '{']];
        yield 'scalar JSON' => [['state' => 'false']];
        yield 'invalid date' => [['created_at' => '2026-02-30 10:00:00.000000']];
    }

    public function testMissingActorHistoryIsExplicit(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);
        self::assertNull(new DoctrineIdentityReadRepository($connection)->actorHistory(Realm::USER, IdentityScenario::id()));
    }
}
