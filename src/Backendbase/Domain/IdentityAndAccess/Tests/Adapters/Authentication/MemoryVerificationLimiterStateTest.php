<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Adapters\Authentication;

use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationClient;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationLimited;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\MemoryVerificationLimiter;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\Time\FrozenClock;

use function range;

final class MemoryVerificationLimiterStateTest extends TestCase
{
    public function testSendCooldownExpiresAtTheSixtiethSecond(): void
    {
        $clock   = new FrozenClock(new DateTimeImmutable('2026-10-14T10:00:00Z'));
        $limiter = new MemoryVerificationLimiter($clock);
        $contact = new ContactValue('email', 'ada@example.com');
        $client  = new VerificationClient('127.0.0.1');
        $limiter->send($contact, $client);
        $clock->advance(new DateInterval('PT59S'));
        self::assertLimited(static fn () => $limiter->send($contact, $client), 1);
        $clock->advance(new DateInterval('PT1S'));
        $limiter->send($contact, $client);
        self::assertLimited(static fn () => $limiter->send($contact, $client), 60);
    }

    public function testConfirmationBudgetExpiresOneHourAfterItsFirstReservation(): void
    {
        $clock   = new FrozenClock(new DateTimeImmutable('2026-10-14T10:00:00Z'));
        $limiter = new MemoryVerificationLimiter($clock);
        $contact = new ContactValue('email', 'ada@example.com');
        $limiter->accept($contact);
        foreach (range(1, 10) as $attempt) {
            $limiter->attempt($contact);
        }

        $clock->advance(new DateInterval('PT59M59S'));
        self::assertLimited(static fn () => $limiter->attempt($contact), 1);
        $clock->advance(new DateInterval('PT1S'));
        foreach (range(1, 10) as $attempt) {
            $limiter->attempt($contact);
        }

        self::assertLimited(static fn () => $limiter->attempt($contact), 3600);
    }

    private static function assertLimited(callable $operation, int $retryAfter): void
    {
        try {
            $operation();
        } catch (VerificationLimited $exception) {
            self::assertSame(['retryAfter' => $retryAfter], $exception->context());

            return;
        }

        self::fail('The reservation must be limited.');
    }
}
