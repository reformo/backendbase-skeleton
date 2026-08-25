<?php

declare(strict_types=1);

namespace Backendbase\Shared\Primitives\Notification;

use JsonSerializable;

interface Notification extends JsonSerializable
{
    public function type(): string;
}
