<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Sessions;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\RedisTokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\Sessions\RedisSessionConnection;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationDenied;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\RefreshToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\Session;
use Backendbase\Domain\IdentityAndAccess\Domain\Sessions\SessionPolicy;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redislabs\Module\RedisJson\RedisJsonInterface;

use function array_replace;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class SessionRecordTest extends TestCase
{
    public function testRedisResponseCannotChangeTheRequestedRealm(): void
    {
        $principal = new Principal(Realm::USER, ['accountId' => IdentityScenario::id(), 'actorId' => IdentityScenario::id(), 'sid' => IdentityScenario::id(), 'jti' => IdentityScenario::id(), 'authVersion' => 1]);
        $token     = RefreshToken::create($principal);
        $session   = Session::start($principal, new SessionPolicy(60, 120, 600), new DateTimeImmutable(), $token);
        $state     = $session->snapshot();
        $client    = $this->createMock(RedisJsonInterface::class);
        $client->expects(self::once())->method('raw')->willReturn(json_encode($state->toArray(), JSON_THROW_ON_ERROR));
        $registry = new RedisTokenRegistry($client);
        $this->expectException(AuthenticationDenied::class);
        $registry->session(Realm::ADMIN, $token);
    }

    #[DataProvider('malformedRecords')]
    public function testCorruptPersistedSessionsFailAtTheRedisBoundary(string $record): void
    {
        $this->expectException(AuthenticationUnavailable::class);
        $this->expectExceptionMessage('The stored session state is invalid.');
        RedisSessionConnection::decode($record);
    }

    /** @return iterable<string, array{string}> */
    public static function malformedRecords(): iterable
    {
        yield 'invalid JSON' => ['{'];
        yield 'scalar' => ['"session"'];
        yield 'empty' => ['{}'];

        $principal = new Principal(Realm::USER, ['accountId' => IdentityScenario::id(), 'actorId' => IdentityScenario::id(), 'sid' => IdentityScenario::id(), 'jti' => IdentityScenario::id(), 'authVersion' => 1]);
        $session   = Session::start($principal, new SessionPolicy(60, 120, 600), new DateTimeImmutable('2026-10-20T10:00:00Z'), RefreshToken::create($principal));
        $state     = $session->snapshot();
        $values    = $state->toArray();
        foreach (self::invalidValues() as $name => $change) {
            yield $name => [json_encode(array_replace($values, $change), JSON_THROW_ON_ERROR)];
        }
    }

    /** @return iterable<string, array<string, mixed>> */
    private static function invalidValues(): iterable
    {
        yield 'unknown version' => ['sessionVersion' => 3];
        yield 'unknown realm' => ['realm' => 'OTHER'];
        yield 'wrong actor type' => ['actorType' => 'admin'];
        yield 'missing policy' => ['policy' => null];
        yield 'unknown state field' => ['unexpected' => true];
        yield 'malformed policy' => ['policy' => ['accessTokenLifetimeSeconds' => 59, 'idleTimeoutSeconds' => 120, 'maximumLifetimeSeconds' => 600]];
        yield 'invalid start' => ['createdAt' => 0];
        yield 'invalid deadline' => ['maximumExpiresAt' => 1];
        yield 'invalid activity' => ['lastUsedAt' => 0];
        yield 'invalid refresh hash' => ['refreshHash' => 'raw-secret'];
    }
}
