<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives\Notification;

use InvalidArgumentException;
use JsonSerializable;
use Override;

use function filter_var;
use function trim;

use const FILTER_VALIDATE_EMAIL;

final readonly class Address implements JsonSerializable
{
    public string $email;

    private function __construct(string $email, public string|null $name = null)
    {
        $email = trim($email);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Invalid email address.');
        }

        $this->email = $email;
    }

    public static function create(string $email, string|null $name = null): self
    {
        $name = $name === null ? null : trim($name);

        return new self($email, $name === '' ? null : $name);
    }

    /** @return array{email: string, name?: string} */
    public function toArray(): array
    {
        $address = ['email' => $this->email];

        if ($this->name !== null) {
            $address['name'] = $this->name;
        }

        return $address;
    }

    /** @return array{email: string, name?: string} */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
