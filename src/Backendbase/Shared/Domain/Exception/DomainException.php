<?php

declare(strict_types=1);

namespace Backendbase\Shared\Domain\Exception;

use DomainException as PhpDomainException;
use Throwable;

abstract class DomainException extends PhpDomainException
{
    /** @param array<string, mixed> $context */
    final private function __construct(
        string $message,
        private readonly array $context,
        Throwable|null $previous,
    ) {
        parent::__construct($message, previous: $previous);
    }

    /** @param array<string, mixed>|null $context */
    public static function create(
        string $message,
        array|null $context = [],
        Throwable|null $previous = null,
    ): static {
        return new static($message, $context ?? [], $previous);
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }
}
