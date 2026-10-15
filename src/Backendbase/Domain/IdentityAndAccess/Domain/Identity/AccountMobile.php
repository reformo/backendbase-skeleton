<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Identity;

use Backendbase\Shared\Exception\InvalidUserInput;

use function preg_match;

final readonly class AccountMobile
{
    public function __construct(private string $value)
    {
        if (preg_match('/\A\+[1-9][0-9]{1,14}\z/D', $value) !== 1) {
            throw InvalidUserInput::create('The account mobile must use E.164 format with 2 to 15 digits after the plus sign.');
        }
    }

    public function toString(): string
    {
        return $this->value;
    }
}
