<?php

declare(strict_types=1);

namespace Backendbase\Shared\Domain\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class DomainEventListener
{
    /** @param class-string<\Backendbase\Shared\Domain\DomainEventListener> $handlerName */
    public function __construct(public string $handlerName)
    {
    }
}
