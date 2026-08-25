<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives;

use Backendbase\Shared\Primitives\Exception\InvalidName;
use Override;
use Stringable;

use function sprintf;
use function strlen;

final readonly class Name implements Stringable
{
    private const int NAME_LENGTH = 2;

    /** @throws InvalidName */
    public function __construct(private string $name)
    {
        if (strlen($name) < self::NAME_LENGTH) {
            throw InvalidName::create(sprintf('Name must be at least %s long', self::NAME_LENGTH));
        }
    }

    #[Override]
    public function __toString(): string
    {
        return $this->name;
    }
}
