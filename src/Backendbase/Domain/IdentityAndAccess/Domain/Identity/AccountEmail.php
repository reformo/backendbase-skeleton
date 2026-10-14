<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Identity;

use Backendbase\Shared\Exception\InvalidUserInput;

use function filter_var;
use function preg_match;
use function strlen;
use function strtolower;

use const FILTER_VALIDATE_EMAIL;

final readonly class AccountEmail
{
    private string $value;

    public function __construct(string $value)
    {
        if (strlen($value) > 254 || preg_match('/\A[\x21-\x7e]+\z/D', $value) !== 1 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidUserInput::create('The account email must be a valid ASCII address with at most 254 characters.');
        }

        $this->value = strtolower($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
