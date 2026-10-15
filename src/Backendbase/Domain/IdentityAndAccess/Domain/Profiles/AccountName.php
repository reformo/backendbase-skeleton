<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Domain\Profiles;

use Backendbase\Shared\Exception\InvalidUserInput;

use function mb_check_encoding;
use function mb_strlen;
use function preg_match;
use function trim;

final readonly class AccountName
{
    public function __construct(private string $value)
    {
        if (! mb_check_encoding($value, 'UTF-8') || mb_strlen($value, 'UTF-8') > 160 || trim($value) === '' || trim($value) !== $value || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
            throw InvalidUserInput::create('Account names require 1 to 160 UTF-8 characters without surrounding spaces or control characters.');
        }
    }

    public function toString(): string
    {
        return $this->value;
    }
}
