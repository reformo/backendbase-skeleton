<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Services;

use Backendbase\Domain\IdentityAndAccess\Adapters\Authentication\RedisVerificationLimiter;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationClient;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationLimited;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Infrastructure\Adapters\Security\SessionRedis;
use PHPUnit\Framework\TestCase;
use Redis;
use Tests\Support\Services\IsolatedRedis;

use function range;
use function str_replace;
use function substr;

final class RedisVerificationLimiterTest extends TestCase
{
    public function testNativeScriptsEnforceContactAndClientBudgetsAndReleaseSuccessfulAttempts(): void
    {
        $identity   = IdentityScenario::id();
        $fixture    = IsolatedRedis::create($identity);
        $client     = $fixture->client();
        $limiter    = new RedisVerificationLimiter(new SessionRedis(static fn (): Redis => $client));
        $contact    = new ContactValue('email', $identity . '@example.com');
        $address    = new VerificationClient('fd00:' . substr(str_replace('-', '', $identity), -4) . '::1');
        $contactKey = $contact->counterKey();
        $cooldown   = 'identity-verification:cooldown:' . $contactKey;
        $keys       = ['identity-verification:send:' . $contactKey, 'identity-verification:ip:' . $address->key(), $cooldown, 'identity-verification:failure:' . $contactKey];
        try {
            $limiter->send($contact, $address);
            self::assertGreaterThan(0, $client->ttl($cooldown));
            self::limited(static fn () => $limiter->send($contact, $address));
            foreach (range(1, 4) as $number) {
                $client->del($cooldown);
                $limiter->send($contact, $address);
            }

            $client->del($cooldown);
            self::limited(static fn () => $limiter->send($contact, $address));
            foreach (range(1, 10) as $number) {
                $limiter->attempt($contact);
            }

            self::limited(static fn () => $limiter->attempt($contact));
            $limiter->accept($contact);
            $limiter->attempt($contact);
            self::assertSame('10', $client->get('identity-verification:failure:' . $contactKey));
            self::assertLessThanOrEqual(3600, $client->ttl('identity-verification:failure:' . $contactKey));
            foreach (range(1, 15) as $number) {
                $other  = new ContactValue('email', $identity . '-' . $number . '@example.com');
                $key    = $other->counterKey();
                $keys[] = 'identity-verification:send:' . $key;
                $keys[] = 'identity-verification:cooldown:' . $key;
                $limiter->send($other, $address);
            }

            self::limited(static fn () => $limiter->send(new ContactValue('email', $identity . '-21@example.com'), $address));
        } finally {
            $client->del($keys);
            $client->close();
        }
    }

    private static function limited(callable $work): void
    {
        try {
            $work();
            self::fail('The native Redis budget must reject this request.');
        } catch (VerificationLimited $exception) {
            self::assertSame('The verification request limit was reached.', $exception->getMessage());
        }
    }
}
