<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Domain\Contacts;

use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactVerificationExpired;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationLimited;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContactDeadlineStateTest extends TestCase
{
    #[DataProvider('codeDeadlines')]
    public function testCodeDeadlineIsExclusive(string $time, bool $allowed): void
    {
        $contact = self::issuedContact();
        if (! $allowed) {
            $this->expectException(ContactVerificationExpired::class);
        }

        $contact->assertConfirmationAllowed(new DateTimeImmutable($time));
        $state = $contact->snapshot();
        self::assertSame('2026-10-14 10:05:00.000000', $state->codeExpiresAt);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function codeDeadlines(): iterable
    {
        yield 'one microsecond before expiry' => ['2026-10-14T10:04:59.999999Z', true];
        yield 'at expiry' => ['2026-10-14T10:05:00.000000Z', false];
        yield 'after expiry' => ['2026-10-14T10:05:00.000001Z', false];
    }

    #[DataProvider('resendDeadlines')]
    public function testResendCooldownAllowsTheExactBoundary(string $time, bool $allowed): void
    {
        $contact = self::issuedContact();
        if (! $allowed) {
            $this->expectException(VerificationLimited::class);
        }

        $contact->assertResendAllowed(new DateTimeImmutable($time));
        $state = $contact->snapshot();
        self::assertSame('2026-10-14 10:00:00.000000', $state->lastSentAt);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function resendDeadlines(): iterable
    {
        yield 'one microsecond before cooldown' => ['2026-10-14T10:00:59.999999Z', false];
        yield 'at cooldown' => ['2026-10-14T10:01:00.000000Z', true];
    }

    #[DataProvider('proofDeadlines')]
    public function testProofMustNotBeFutureOrTwelveHoursOld(string $time, bool $allowed): void
    {
        $contact = self::issuedContact();
        $contact->confirm(true, new DateTimeImmutable('2026-10-14T10:00:00Z'));
        if (! $allowed) {
            $this->expectException(ContactVerificationExpired::class);
        }

        $contact->assertFresh(new DateTimeImmutable($time));
        $state = $contact->snapshot();
        self::assertSame('2026-10-14 10:00:00.000000', $state->verifiedAt);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function proofDeadlines(): iterable
    {
        yield 'future by one microsecond' => ['2026-10-14T09:59:59.999999Z', false];
        yield 'at confirmation' => ['2026-10-14T10:00:00.000000Z', true];
        yield 'one microsecond before expiry' => ['2026-10-14T21:59:59.999999Z', true];
        yield 'at expiry' => ['2026-10-14T22:00:00.000000Z', false];
        yield 'after expiry' => ['2026-10-14T22:00:00.000001Z', false];
    }

    private static function issuedContact(): ContactInfo
    {
        $now     = new DateTimeImmutable('2026-10-14T10:00:00Z');
        $contact = ContactInfo::request(IdentityId::create(), new ContactValue('email', 'ada@example.com'), new VerificationContextToken(ContactScenario::token()), new VerificationScope(Realm::USER), $now);
        $contact->issue('generation', 'hash', $now);

        return $contact;
    }
}
