<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Application\Contacts;

use Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel\ContactInfoView;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationContextToken;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\VerificationScope;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\IdentityId;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\ContactScenario;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ContactVerificationViewTest extends TestCase
{
    public function testResponseDeadlinesFollowTheProofAndResendPolicy(): void
    {
        $issuedAt = new DateTimeImmutable('2026-10-14T10:00:00Z');
        $contact  = ContactInfo::request(IdentityId::create(), new ContactValue('email', 'ada@example.com'), new VerificationContextToken(ContactScenario::token()), new VerificationScope(Realm::USER), $issuedAt);
        $contact->issue('generation', 'hash', $issuedAt);
        $view    = ContactInfoView::fromContact($contact);
        $pending = $view->jsonSerialize();
        self::assertSame('2026-10-14T10:05:00.000000Z', $pending['codeExpiresAt']);
        self::assertSame('2026-10-14T10:01:00.000000Z', $pending['resendAfter']);
        self::assertNull($pending['verifiedAt']);
        self::assertNull($pending['registrationValidUntil']);
        $contact->confirm(true, new DateTimeImmutable('2026-10-14T10:04:00Z'));
        $view     = ContactInfoView::fromContact($contact);
        $verified = $view->jsonSerialize();
        self::assertSame('2026-10-14T10:04:00.000000Z', $verified['verifiedAt']);
        self::assertSame('2026-10-14T22:04:00.000000Z', $verified['registrationValidUntil']);
        self::assertSame('2026-10-14T10:01:00.000000Z', $verified['resendAfter']);
    }
}
