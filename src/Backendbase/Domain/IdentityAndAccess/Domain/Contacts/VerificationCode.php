<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Contacts;

use Backendbase\Shared\Exception\InvalidUserInput;

use function preg_match;
use function random_int;
use function sprintf;

final readonly class VerificationCode
{
    public function __construct(private string $value)
    {
        if (preg_match('/\A[0-9]{6}\z/D', $value) !== 1) {
            throw InvalidUserInput::create('Supply a six-digit verification code.');
        }
    }

    public static function generate(): self
    {
        return new self(sprintf('%06d', random_int(0, 999999)));
    }

    public function proof(ContactInfo $contact): string
    {
        $state = $contact->snapshot();
        $id    = $contact->id();

        return $id . ':' . $state['generationId'] . ':' . $state['realm'] . ':' . $state['purpose'] . ':' . $state['contactType'] . ':' . $state['contactValue'] . ':' . $this->value;
    }

    public function forDelivery(): string
    {
        return $this->value;
    }
}
