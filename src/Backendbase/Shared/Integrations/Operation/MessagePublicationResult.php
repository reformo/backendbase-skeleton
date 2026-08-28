<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations\Operation;

final readonly class MessagePublicationResult
{
    public function __construct(private string|null $messageId)
    {
    }

    public function messageId(): string|null
    {
        return $this->messageId;
    }
}
