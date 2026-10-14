<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Identity;

use Backendbase\Shared\Exception\InvalidUserInput;

use function mb_check_encoding;
use function mb_strlen;
use function str_contains;
use function strlen;

final readonly class Password
{
    public function __construct(private string $value)
    {
        if (! mb_check_encoding($value, 'UTF-8') || mb_strlen($value, 'UTF-8') < 12 || mb_strlen($value, 'UTF-8') > 128 || strlen($value) > 512 || str_contains($value, "\0")) {
            throw InvalidUserInput::create('The password must contain 12 to 128 UTF-8 characters and no null byte.');
        }
    }

    public function forHashing(): string
    {
        return $this->value;
    }
}
