<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Domain\Contacts;

use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationLimited;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContactEvidenceStateTest extends TestCase
{
    public function testResendPreservesFailuresAndTheFifthFailureBlocksFurtherWork(): void
    {
        $now     = new DateTimeImmutable('2026-10-14T10:00:00Z');
        $contact = self::contact($now);
        $contact->issue('first', 'hash-one', $now);
        self::assertFalse($contact->confirm(false, $now));
        self::assertFalse($contact->confirm(false, $now));
        $resendAt = new DateTimeImmutable('2026-10-14T10:01:00Z');
        $contact->assertResendAllowed($resendAt);
        $contact->issue('second', 'hash-two', $resendAt);
        $state = $contact->snapshot();
        self::assertSame(2, $state->failedAttempts);
        foreach ([1, 2, 3] as $attempt) {
            self::assertFalse($contact->confirm(false, $resendAt));
        }

        $state = $contact->snapshot();
        self::assertSame(5, $state->failedAttempts);
        try {
            $contact->assertConfirmationAllowed($resendAt);
            self::fail('Five failures must block confirmation.');
        } catch (VerificationLimited $exception) {
            self::assertSame(['retryAfter' => 3600], $exception->context());
        }

        $this->expectException(VerificationLimited::class);
        $contact->assertResendAllowed(new DateTimeImmutable('2026-10-14T10:02:00Z'));
    }

    #[DataProvider('deliveryResults')]
    public function testStaleDeliveryCannotChangeTheCurrentGenerationOrProof(string $oldResult, string $currentResult): void
    {
        $now      = new DateTimeImmutable('2026-10-14T10:00:00Z');
        $contact  = self::contact($now);
        $resendAt = new DateTimeImmutable('2026-10-14T10:01:00Z');
        $contact->issue('first', 'hash-one', $now);
        $contact->assertResendAllowed($resendAt);
        $contact->issue('second', 'hash-two', $resendAt);
        $contact->recordDelivery('first', $oldResult);
        $pending = $contact->snapshot();
        self::assertSame('second', $pending->generationId);
        self::assertSame('pending', $pending->deliveryState);
        self::assertNull($pending->verifiedAt);
        self::assertTrue($contact->confirm(true, $resendAt));
        $contact->recordDelivery('second', $currentResult);
        $verified = $contact->snapshot();
        self::assertSame($currentResult, $verified->deliveryState);
        self::assertSame('2026-10-14 10:01:00.000000', $verified->verifiedAt);
    }

    /** @return iterable<string, array{string, string}> */
    public static function deliveryResults(): iterable
    {
        yield 'old accepted, current accepted' => ['accepted', 'accepted'];
        yield 'old accepted, current unknown' => ['accepted', 'unknown'];
        yield 'old unknown, current accepted' => ['unknown', 'accepted'];
        yield 'old unknown, current unknown' => ['unknown', 'unknown'];
    }

    private static function contact(DateTimeImmutable $now): ContactInfo
    {
        return ContactInfo::request(IdentityId::create(), new ContactValue('email', 'ada@example.com'), new VerificationContextToken(ContactScenario::token()), new VerificationScope(Realm::USER), $now);
    }
}
