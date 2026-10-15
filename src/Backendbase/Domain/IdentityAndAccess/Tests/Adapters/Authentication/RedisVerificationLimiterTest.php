<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Authentication;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\RedisVerificationLimiter;
use Backendbase\Domain\IdentityAndAccess\Domain\Authentication\AuthenticationUnavailable;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationClient;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationLimited;
use Backendbase\Infrastructure\Adapters\Security\SessionRedis;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redis;
use RedisException;
use Throwable;

use function implode;

final class RedisVerificationLimiterTest extends TestCase
{
    public function testRequestsAndConfirmationsUseStablePrivateKeys(): void
    {
        $contact = new ContactValue('email', 'ada@example.com');
        $address = new VerificationClient('127.0.0.1');
        $redis   = $this->createMock(Redis::class);
        $calls   = [];
        $redis->expects(self::exactly(3))->method('eval')->willReturnCallback(static function (string $script, array $keys, int $count) use (&$calls): int {
            $calls[] = [$script, $keys, $count];

            return 0;
        });
        $limiter = new RedisVerificationLimiter(new SessionRedis(static fn (): Redis => $redis));
        $limiter->send($contact, $address);
        $limiter->attempt($contact);
        $limiter->accept($contact);
        self::assertSame(['identity-verification:send:' . $contact->counterKey(), 'identity-verification:ip:' . $address->key(), 'identity-verification:cooldown:' . $contact->counterKey()], $calls[0][1]);
        self::assertSame(3, $calls[0][2]);
        self::assertSame($calls[1][1], $calls[2][1]);
        self::assertStringNotContainsString('ada@example.com', implode('', $calls[0][1]));
        self::assertStringContainsString("redis.call('EXPIRE', key, 3600)", $calls[0][0]);
        self::assertStringContainsString("redis.call('DECR', KEYS[1])", $calls[2][0]);
    }

    /** @param class-string<Throwable> $exception */
    #[DataProvider('replies')]
    public function testLimitAndUnavailableRepliesFailExplicitly(int|bool $reply, string $exception): void
    {
        $redis = $this->createStub(Redis::class);
        $redis->method('eval')->willReturn($reply);
        $limiter = new RedisVerificationLimiter(new SessionRedis(static fn (): Redis => $redis));
        $this->expectException($exception);
        $limiter->attempt(new ContactValue('mobile', '+905551234567'));
    }

    public function testConnectionFailureIsTranslatedWithoutChangingItsCause(): void
    {
        $cause   = new RedisException('Unavailable test connection.');
        $limiter = new RedisVerificationLimiter(new SessionRedis(static function () use ($cause): Redis {
            throw $cause;
        }));
        try {
            $limiter->attempt(new ContactValue('email', 'ada@example.com'));
            self::fail('Unavailable controls must reject verification.');
        } catch (AuthenticationUnavailable $exception) {
            self::assertSame($cause, $exception->getPrevious());
        }
    }

    /** @return iterable<string, array{int|bool, class-string<Throwable>}> */
    public static function replies(): iterable
    {
        yield 'cooldown' => [60, VerificationLimited::class];
        yield 'invalid negative result' => [-1, AuthenticationUnavailable::class];
        yield 'malformed result' => [false, AuthenticationUnavailable::class];
    }
}
