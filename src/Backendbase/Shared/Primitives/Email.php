<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives;

use Backendbase\Shared\Primitives\Exception\InvalidEmailAddress;
use Override;
use Stringable;

use function filter_var;

use const FILTER_VALIDATE_EMAIL;

final readonly class Email implements Stringable
{
    public function __construct(private string $email)
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidEmailAddress::create('Email provided is not a valid e-mail address');
        }
    }

    public function toString(): string
    {
        return $this->email;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->toString();
    }
}
