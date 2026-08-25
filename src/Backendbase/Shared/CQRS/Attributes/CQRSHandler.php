<?php

declare(strict_types=1);

namespace Backendbase\Shared\CQRS\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class CQRSHandler
{
    public function __construct(public string $handlerName)
    {
    }
}
