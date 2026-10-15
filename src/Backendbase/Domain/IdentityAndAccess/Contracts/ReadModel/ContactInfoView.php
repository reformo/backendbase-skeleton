<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Contracts\ReadModel;

use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactInfo;
use DateTimeImmutable;
use DateTimeZone;
use JsonSerializable;

final readonly class ContactInfoView implements JsonSerializable
{
    /** @param array{contactInfoId: string, contactType: string, codeExpiresAt: string, resendAfter: string, verifiedAt: string|null, registrationValidUntil: string|null, deliveryState: string} $values */
    public function __construct(private array $values)
    {
    }

    public static function fromContact(ContactInfo $contact): self
    {
        $state      = $contact->snapshot();
        $verified   = $state['verifiedAt'] === null ? null : new DateTimeImmutable($state['verifiedAt'], new DateTimeZone('UTC'));
        $sent       = new DateTimeImmutable($state['lastSentAt'], new DateTimeZone('UTC'));
        $resend     = $sent->modify('+60 seconds');
        $expires    = new DateTimeImmutable($state['codeExpiresAt'], new DateTimeZone('UTC'));
        $validUntil = $state['purpose'] === 'user-registration' ? $verified?->modify('+12 hours') : null;

        return new self(['contactInfoId' => $contact->id(), 'contactType' => $state['contactType'], 'codeExpiresAt' => $expires->format('Y-m-d\TH:i:s.u\Z'), 'resendAfter' => $resend->format('Y-m-d\TH:i:s.u\Z'), 'verifiedAt' => $verified?->format('Y-m-d\TH:i:s.u\Z'), 'registrationValidUntil' => $validUntil?->format('Y-m-d\TH:i:s.u\Z'), 'deliveryState' => $state['deliveryState']]);
    }

    /** @return array{contactInfoId: string, contactType: string, codeExpiresAt: string, resendAfter: string, verifiedAt: string|null, registrationValidUntil: string|null, deliveryState: string} */
    public function jsonSerialize(): array
    {
        return $this->values;
    }
}
