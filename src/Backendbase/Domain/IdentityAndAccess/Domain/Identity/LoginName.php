<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Identity;

use Backendbase\Shared\Exception\InvalidUserInput;

use function preg_match;
use function strtolower;

final readonly class LoginName
{
    private string $value;

    public function __construct(string $value)
    {
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{2,63}\z/D', $value) !== 1) {
            throw InvalidUserInput::create('The login name must contain 3 to 64 ASCII letters, digits, dots, underscores, or hyphens.');
        }

        $this->value = strtolower($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
