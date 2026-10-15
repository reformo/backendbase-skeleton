<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\StateField;
use Backendbase\Domain\IdentityAndAccess\Adapters\ReadModel\DoctrineIdentityReadRepository;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class PersistedIdentityStateTest extends TestCase
{
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
        $row        = ['uuid' => $id->toString(), 'account_id' => $id->getBytes(), 'normalized_email' => 'test@example.com', 'normalized_mobile' => '+905551234567', 'first_name' => null, 'last_name' => null, 'created_at' => '2026-10-14 10:00:00.000000', 'deleted_at' => null, 'state' => '{"approved":true}'];
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
