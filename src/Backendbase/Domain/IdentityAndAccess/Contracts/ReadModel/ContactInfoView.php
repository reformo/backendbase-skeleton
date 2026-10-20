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
        $verifiedAt = $state->verifiedAt;
        $verified   = $verifiedAt === null ? null : new DateTimeImmutable($verifiedAt, new DateTimeZone('UTC'));
        $sent       = new DateTimeImmutable($state->lastSentAt, new DateTimeZone('UTC'));
        $resend     = $sent->modify('+60 seconds');
        $expires    = new DateTimeImmutable($state->codeExpiresAt, new DateTimeZone('UTC'));
        $purpose    = $state->purpose;
        $validUntil = $purpose === 'user-registration' ? $verified?->modify('+12 hours') : null;

        $id                     = $contact->id();
        $type                   = $state->contactType;
        $expiresAt              = $expires->format('Y-m-d\TH:i:s.u\Z');
        $resendAfter            = $resend->format('Y-m-d\TH:i:s.u\Z');
        $verifiedAt             = $verified?->format('Y-m-d\TH:i:s.u\Z');
        $registrationValidUntil = $validUntil?->format('Y-m-d\TH:i:s.u\Z');

        return new self(['contactInfoId' => $id, 'contactType' => $type, 'codeExpiresAt' => $expiresAt, 'resendAfter' => $resendAfter, 'verifiedAt' => $verifiedAt, 'registrationValidUntil' => $registrationValidUntil, 'deliveryState' => $state->deliveryState]);
    }

    /** @return array{contactInfoId: string, contactType: string, codeExpiresAt: string, resendAfter: string, verifiedAt: string|null, registrationValidUntil: string|null, deliveryState: string} */
    public function jsonSerialize(): array
    {
        return $this->values;
    }
}
