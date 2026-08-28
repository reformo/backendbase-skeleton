<?php

declare(strict_types=1);

namespace Backendbase\Shared\Domain\Exception;

use DomainException as PhpDomainException;

abstract class DomainException extends PhpDomainException
{
    /** @param array<string, mixed> $context */
    final private function __construct(string $message, private readonly array $context)
    {
        parent::__construct($message);
    }

    /** @param array<string, mixed>|null $context */
    public static function create(string $message, array|null $context = []): static
    {
        return new static($message, $context ?? []);
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }
}
