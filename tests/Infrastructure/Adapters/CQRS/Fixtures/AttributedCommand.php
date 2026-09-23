<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\CQRS\Fixtures;

use Backendbase\Shared\CQRS\Attributes\CQRSHandler;
use Backendbase\Shared\CQRS\Command;
use Backendbase\Shared\CQRS\CommandHandler;

#[CQRSHandler(CommandHandler::class)]
final readonly class AttributedCommand implements Command
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
