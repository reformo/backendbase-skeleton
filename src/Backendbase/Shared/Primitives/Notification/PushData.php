<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives\Notification;

use InvalidArgumentException;

use function is_scalar;
use function is_string;
use function trim;

final readonly class PushData
{
    /** @var array<non-empty-string, scalar> */
    private array $values;

    /** @param array<array-key, mixed> $values */
    public function __construct(array $values)
    {
        $validated = [];
        foreach ($values as $key => $value) {
            if (! is_string($key) || $key === '' || trim($key) === '' || ! is_scalar($value)) {
                throw new InvalidArgumentException('Push data requires non-empty keys and scalar values.');
            }

            $validated[$key] = $value;
        }

        $this->values = $validated;
    }

    /** @return array<non-empty-string, scalar> */
    public function toArray(): array
    {
        return $this->values;
    }

    /** @return array<non-empty-string, string> */
    public function strings(): array
    {
        $strings = [];
        foreach ($this->values as $key => $value) {
            $strings[$key] = (string) $value;
        }

        return $strings;
    }

    public function notificationImage(): string|null
    {
        $image = $this->values['notificationImage'] ?? null;

        return is_string($image) ? $image : null;
    }
}
