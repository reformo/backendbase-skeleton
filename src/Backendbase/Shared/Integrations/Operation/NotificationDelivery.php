<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations\Operation;

final readonly class NotificationDelivery
{
    public function __construct(private string $type, private string|null $messageId)
    {
    }

    public function type(): string
    {
        return $this->type;
    }

    public function messageId(): string|null
    {
        return $this->messageId;
    }
}
