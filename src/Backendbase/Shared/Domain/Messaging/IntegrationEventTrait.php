<?php

declare(strict_types=1);

namespace Backendbase\Shared\Domain\Messaging;

use DateTimeImmutable;

use function defined;

trait IntegrationEventTrait
{
    private DateTimeImmutable $occurredOn;

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    // phpcs:enable

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->getEventArguments();
    }

    public function isExternal(): bool
    {
        return defined('static::IS_MESSAGING_EVENT') && static::IS_MESSAGING_EVENT;
    }
}
