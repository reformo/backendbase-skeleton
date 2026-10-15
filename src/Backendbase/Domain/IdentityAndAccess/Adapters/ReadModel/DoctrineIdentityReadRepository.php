<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Adapters\ReadModel;

use Backendbase\Domain\IdentityAndAccess\Adapters\Persistence\Doctrine\StateField;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityReadRepository;
use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\ActorHistory;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use JsonException;
use Ramsey\Uuid\Uuid;

use function is_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final readonly class DoctrineIdentityReadRepository implements IdentityReadRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function actorHistory(Realm $realm, string $actorId): ActorHistory|null
    {
        $table      = $realm === Realm::USER ? 'identity_user' : 'identity_admin';
        $uuid       = Uuid::fromString($actorId);
        $connection = $this->connection;
        $row        = $connection->fetchAssociative('SELECT actor.uuid, actor.account_id, account.normalized_email, account.normalized_mobile, account.first_name, account.last_name, actor.created_at, actor.deleted_at, actor.state FROM ' . $table . ' actor JOIN identity_account account ON account.id = actor.account_id WHERE actor.id = :id', ['id' => $uuid->getBytes()], ['id' => ParameterType::BINARY]);
        if ($row === false) {
            return null;
        }

        $accountBytes = StateField::string($row, 'account_id');
        $accountUuid  = Uuid::fromBytes($accountBytes);
        $id           = IdentityId::fromString(StateField::string($row, 'uuid'));
        try {
            $state = json_decode(StateField::string($row, 'state'), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw AuthenticationUnavailable::create('The persisted actor history is invalid.', previous: $exception);
        }

        if (! is_array($state)) {
            throw AuthenticationUnavailable::create('The persisted actor history is invalid.');
        }

        return new ActorHistory([
            'actorId' => $id->toString(),
            'accountId' => $accountUuid->toString(),
            'realm' => $realm->value,
            'email' => StateField::string($row, 'normalized_email'),
            'mobile' => StateField::string($row, 'normalized_mobile'),
            'firstName' => StateField::nullableString($row, 'first_name'),
            'lastName' => StateField::nullableString($row, 'last_name'),
            'approved' => StateField::boolean($state, 'approved'),
            'createdAt' => self::date(StateField::string($row, 'created_at')),
            'deletedAt' => $row['deleted_at'] === null ? null : self::date(StateField::string($row, 'deleted_at')),
        ]);
    }

    private static function date(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $value, new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d H:i:s.u') !== $value) {
            throw AuthenticationUnavailable::create('The persisted actor date is invalid.');
        }

        return $date->format('Y-m-d\TH:i:s.u\Z');
    }
}
