<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Identity;

use function str_contains;

final readonly class LoginIdentifier
{
    private AccountEmail|AccountMobile $contact;

    public function __construct(string $value)
    {
        $this->contact = str_contains($value, '@') ? new AccountEmail($value) : new AccountMobile($value);
    }

    public function contact(): AccountEmail|AccountMobile
    {
        return $this->contact;
    }
}
