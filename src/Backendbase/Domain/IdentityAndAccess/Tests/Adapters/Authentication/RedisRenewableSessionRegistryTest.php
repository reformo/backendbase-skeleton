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
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redislabs\Module\RedisJson\RedisJsonInterface;

use function count;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class RedisRenewableSessionRegistryTest extends TestCase
{
    public function testActivationLookupAndRotationKeepRefreshCredentialsSecret(): void
    {
        $principal = self::principal();
        $now       = new DateTimeImmutable('2026-11-02T10:00:00Z');
        $refresh   = RefreshToken::create($principal);
        $session   = self::session($principal, $refresh, $now);
        $original  = $session->snapshot();
        $expires   = $session->tokenExpiresAt($now);
        $newToken  = RefreshToken::create($principal);
        $session->rotate($newToken, $now);
        $replacement = $session->snapshot();
        $calls       = [];
        $client      = $this->createMock(RedisJsonInterface::class);
        $evaluation  = $client->expects(self::exactly(4));
        $evaluation->method('raw');
        $evaluation->willReturnCallback(static function (string $command, string $script, int $keyCount, string|int ...$arguments) use (&$calls, $original, $replacement): int|string {
            $calls[] = $arguments;

            return match (count($calls)) {
                1, 3 => 1,
                2 => json_encode($original->toArray(), JSON_THROW_ON_ERROR),
                4 => json_encode($replacement->toArray(), JSON_THROW_ON_ERROR),
                default => throw new LogicException('Unexpected Redis call.'),
            };
        });
        $registry = new RedisTokenRegistry($client);
        $registry->activate($principal, $expires, $original);
        $loaded         = $registry->session(Realm::USER, $refresh);
        $originalValues = $original->toArray();
        $loadedValues   = $loaded->toArray();
        self::assertSame($originalValues, $loadedValues);
        $registry->rotate($refresh, $replacement, $expires);
        $replayed          = $registry->session(Realm::USER, $newToken, true);
        $replacementValues = $replacement->toArray();
        $replayedValues    = $replayed->toArray();
        self::assertSame($replacementValues, $replayedValues);
        self::assertSame(0, $calls[1][4]);
        self::assertSame(1, $calls[3][4]);
        $sent = json_encode($calls, JSON_THROW_ON_ERROR);
        self::assertStringContainsString($refresh->digest(), $sent);
        self::assertStringContainsString($newToken->digest(), $sent);
        self::assertStringNotContainsString($refresh->expose(), $sent);
        self::assertStringNotContainsString($newToken->expose(), $sent);
    }

    /** @param class-string<AuthenticationDenied|AuthenticationUnavailable> $expected */
    #[DataProvider('rejectedWrites')]
    public function testRejectedSessionWritesDoNotReportSuccess(string $operation, int|false $result, string $expected): void
    {
        $principal  = self::principal();
        $refresh    = RefreshToken::create($principal);
        $now        = new DateTimeImmutable('2026-11-02T10:00:00Z');
        $session    = self::session($principal, $refresh, $now);
        $state      = $session->snapshot();
        $expires    = $session->tokenExpiresAt($now);
        $client     = $this->createStub(RedisJsonInterface::class);
        $evaluation = $client->method('raw');
        $evaluation->willReturn($result);
        $registry = new RedisTokenRegistry($client);
        $this->expectException($expected);
        if ($operation === 'activation') {
            $registry->activate($principal, $expires, $state);

            return;
        }

        $registry->rotate($refresh, $state, $expires);
    }

    /** @return iterable<string, array{string, int|false, class-string<AuthenticationDenied|AuthenticationUnavailable>}> */
    public static function rejectedWrites(): iterable
    {
        yield 'activation denied' => ['activation', 0, AuthenticationDenied::class];
        yield 'rotation denied' => ['rotation', 0, AuthenticationDenied::class];
        yield 'activation unavailable' => ['activation', false, AuthenticationUnavailable::class];
        yield 'rotation unavailable' => ['rotation', false, AuthenticationUnavailable::class];
    }

    private static function principal(): Principal
    {
        return Principal::start(Realm::USER, IdentityScenario::id(), IdentityScenario::id(), 1);
    }

    private static function session(Principal $principal, RefreshToken $refresh, DateTimeImmutable $now): Session
    {
        return Session::start($principal, new SessionPolicy(60, 120, 600), $now, $refresh);
    }
}
