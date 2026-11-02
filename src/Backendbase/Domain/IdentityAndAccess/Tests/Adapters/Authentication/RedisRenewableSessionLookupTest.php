<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Authentication;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\RedisTokenRegistry;
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

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class RedisRenewableSessionLookupTest extends TestCase
{
    /** @param class-string<AuthenticationDenied|AuthenticationUnavailable> $expected */
    #[DataProvider('invalidLookups')]
    public function testLookupRejectsMissingMalformedOrMismatchedSession(string $scenario, string $expected): void
    {
        $principal = Principal::start(Realm::USER, IdentityScenario::id(), IdentityScenario::id(), 1);
        $refresh   = RefreshToken::create($principal);
        $identity  = $principal->identity();
        if ($scenario === 'account') {
            $identity['accountId'] = IdentityScenario::id();
        }

        if ($scenario === 'session') {
            $identity['sid'] = IdentityScenario::id();
        }

        $realm        = $scenario === 'realm' ? Realm::ADMIN : Realm::USER;
        $foreign      = new Principal($realm, $identity);
        $foreignToken = RefreshToken::create($foreign);
        $session      = Session::start($foreign, new SessionPolicy(60, 120, 600), new DateTimeImmutable('2026-11-02T10:00:00Z'), $foreignToken);
        $state        = $session->snapshot();
        $result       = match ($scenario) {
            'missing' => 0,
            'malformed' => '{invalid json',
            default => json_encode($state->toArray(), JSON_THROW_ON_ERROR),
        };
        $client     = $this->createStub(RedisJsonInterface::class);
        $evaluation = $client->method('raw');
        $evaluation->willReturn($result);
        $registry = new RedisTokenRegistry($client);
        $this->expectException($expected);
        $registry->session(Realm::USER, $refresh);
    }

    /** @return iterable<string, array{string, class-string<AuthenticationDenied|AuthenticationUnavailable>}> */
    public static function invalidLookups(): iterable
    {
        yield 'missing' => ['missing', AuthenticationDenied::class];
        yield 'malformed' => ['malformed', AuthenticationUnavailable::class];
        yield 'wrong realm' => ['realm', AuthenticationDenied::class];
        yield 'wrong account' => ['account', AuthenticationDenied::class];
        yield 'wrong session' => ['session', AuthenticationDenied::class];
    }
}
