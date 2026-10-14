<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\RedisTokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\Principal;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RedisException;
use Redislabs\Module\RedisJson\RedisJsonInterface;

final class RedisFailureTest extends TestCase
{
    #[DataProvider('failureModes')]
    public function testUnavailableAuthenticationStateCannotIssueASession(bool $exception): void
    {
        $client     = $this->createMock(RedisJsonInterface::class);
        $evaluation = $client->expects(self::once())->method('raw');
        if ($exception) {
            $evaluation->willThrowException(new RedisException('provider details'));
        }

        if (! $exception) {
            $evaluation->willReturn(false);
        }

        $registry  = new RedisTokenRegistry($client);
        $principal = new Principal(Realm::USER, ['accountId' => IdentityScenario::id(), 'actorId' => IdentityScenario::id(), 'jti' => IdentityScenario::id(), 'sid' => IdentityScenario::id(), 'authVersion' => 1]);
        $this->expectException(AuthenticationUnavailable::class);
        $this->expectExceptionMessage('Authentication state is temporarily unavailable.');
        $registry->activate($principal, new DateTimeImmutable('+15 minutes'));
    }

    /** @return iterable<string, array{bool}> */
    public static function failureModes(): iterable
    {
        yield 'connection exception' => [true];
        yield 'failed SDK response' => [false];
    }
}
