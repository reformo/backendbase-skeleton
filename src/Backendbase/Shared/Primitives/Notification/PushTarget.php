<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives\Notification;

use InvalidArgumentException;

use function trim;

final readonly class PushTarget
{
    /** @param non-empty-string $value */
    private function __construct(private string $type, private string $value)
    {
    }

    public static function topic(string $topic): self
    {
        if (trim($topic) === '') {
            throw new InvalidArgumentException('The push topic cannot be empty.');
        }

        return new self('topic', $topic);
    }

    public static function deviceToken(string $deviceToken): self
    {
        if (trim($deviceToken) === '') {
            throw new InvalidArgumentException('The push device token cannot be empty.');
        }

        return new self('token', $deviceToken);
    }

    public static function fromValues(string|null $topic, string|null $deviceToken): self
    {
        if ($topic !== null && $deviceToken === null) {
            return self::topic($topic);
        }

        if ($deviceToken !== null && $topic === null) {
            return self::deviceToken($deviceToken);
        }

        throw new InvalidArgumentException('A push notification requires exactly one target.');
    }

    public function type(): string
    {
        return $this->type;
    }

    /** @return non-empty-string */
    public function value(): string
    {
        return $this->value;
    }
}
