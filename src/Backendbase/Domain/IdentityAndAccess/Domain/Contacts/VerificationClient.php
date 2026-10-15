<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Contacts;

use Backendbase\Shared\Exception\InvalidUserInput;

use function filter_var;
use function hash;
use function inet_pton;

use const FILTER_VALIDATE_IP;

final readonly class VerificationClient
{
    private string $key;

    public function __construct(string $address)
    {
        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            throw InvalidUserInput::create('A valid direct client address is required.');
        }

        $this->key = hash('sha256', (string) inet_pton($address));
    }

    public function key(): string
    {
        return $this->key;
    }
}
