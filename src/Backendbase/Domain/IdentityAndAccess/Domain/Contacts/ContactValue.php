<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Contacts;

use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountEmail;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\AccountMobile;
use Backendbase\Domain\IdentityAndAccess\Domain\Primitives\ContactState;
use Backendbase\Shared\Exception\InvalidUserInput;

use function hash;

final readonly class ContactValue
{
    private string $value;

    public function __construct(private string $type, string $value)
    {
        $contact = match ($type) {
            'email' => new AccountEmail($value),
            'mobile' => new AccountMobile($value),
            default => throw InvalidUserInput::create('Select email or mobile.'),
        };
        $this->value = $contact->toString();
    }

    public function type(): string
    {
        return $this->type;
    }

    public static function fromState(ContactState $state): self
    {
        $type = $state->contactType;

        return new self($type, $state->contactValue);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function counterKey(): string
    {
        return hash('sha256', $this->type . ':' . $this->value);
    }
}
