<?php

declare(strict_types=1);

namespace Tests\Support\Services\RedisFailover;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\RedisTokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationGate;
use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationTokens;
use Backendbase\Domain\IdentityAndAccess\Contracts\IdentityStore;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenCodec;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\Assert;

final class AuthenticationSessions
{
    /** @param array<string, array{principal: Principal, token: string}> $sessions */
    private function __construct(private readonly IdentityFixture $identity, private array $sessions)
    {
    }

    public static function issue(Realm $realm, RedisTokenRegistry $registry): self
    {
        $identity = IdentityFixture::create();
        IdentityScenario::admin($identity);
        $principal = IdentityScenario::principal($identity, $realm, 'ada.admin');
        $values    = $principal->identity();
        $sessions  = new self($identity, []);
        $tokens    = $sessions->tokens($registry);
        foreach (['active', 'revoked', 'loggedOut'] as $name) {
            $current                   = new Principal($realm, [...$values, 'jti' => IdentityScenario::id(), 'sid' => IdentityScenario::id()]);
            $sessions->sessions[$name] = ['principal' => $current, 'token' => $tokens->issue($current, new DateTimeImmutable('+15 minutes'))];
        }

        return $sessions;
    }

    public function revoke(RedisTokenRegistry $registry): void
    {
        $registry->revokeToken($this->principal('revoked'));
        $registry->revokeSession($this->principal('loggedOut'));
    }

    public function principal(string $name = 'active'): Principal
    {
        return $this->sessions[$name]['principal'];
    }

    public function token(string $name = 'active'): string
    {
        return $this->sessions[$name]['token'];
    }

    public function gate(RedisTokenRegistry $registry): AuthenticationGate
    {
        $identity  = $this->identity;
        $container = $identity->container();

        return new AuthenticationGate($container->get(IdentityStore::class), $this->tokens($registry));
    }

    public function assertReplicatedState(RedisTokenRegistry $registry): void
    {
        $gate      = $this->gate($registry);
        $principal = $this->principal();
        $realm     = $principal->realm();
        $verified  = $gate->verify($realm, $this->token());
        Assert::assertSame($principal->claims(), $verified->claims());
        AuthenticationAssertions::denied(fn () => $gate->verify($realm, $this->token('revoked')));
        AuthenticationAssertions::denied(fn () => $gate->verify($realm, $this->token('loggedOut')));
    }

    public function assertAllDenied(RedisTokenRegistry $registry): void
    {
        $gate      = $this->gate($registry);
        $principal = $this->principal();
        $realm     = $principal->realm();
        foreach ($this->sessions as $session) {
            AuthenticationAssertions::denied(static fn () => $gate->verify($realm, $session['token']));
        }
    }

    public function assertNewLogin(RedisTokenRegistry $registry): void
    {
        $identity  = $this->identity;
        $principal = $this->principal();
        $realm     = $principal->realm();
        $current   = IdentityScenario::principal($identity, $realm, 'ada.admin');
        $tokens    = $this->tokens($registry);
        $token     = $tokens->issue($current, new DateTimeImmutable('+15 minutes'));
        $gate      = $this->gate($registry);
        $verified  = $gate->verify($realm, $token);
        Assert::assertSame($current->claims(), $verified->claims());
    }

    public function assertReadOnlyEndpoint(RedisTokenRegistry $registry): void
    {
        $principal = $this->principal();
        $values    = $principal->identity();
        $fresh     = new Principal($principal->realm(), [...$values, 'jti' => IdentityScenario::id(), 'sid' => IdentityScenario::id()]);
        AuthenticationAssertions::unavailable(static fn () => $registry->activate($fresh, new DateTimeImmutable('+15 minutes')));
    }

    public function tokenKey(): string
    {
        $principal = $this->principal();
        $identity  = $principal->identity();
        $realm     = $principal->realm();

        return 'identity:{' . $realm->value . ':' . $identity['accountId'] . '}:jti:' . $identity['jti'];
    }

    private function tokens(RedisTokenRegistry $registry): AuthenticationTokens
    {
        $identity  = $this->identity;
        $container = $identity->container();

        return new AuthenticationTokens($container->get(TokenCodec::class), $registry);
    }
}
