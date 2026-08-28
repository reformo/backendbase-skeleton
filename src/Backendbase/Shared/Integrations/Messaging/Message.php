<?php

declare(strict_types=1);

namespace Backendbase\Shared\Integrations\Messaging;

final readonly class Message
{
    /**
     * @var array{
     *     body: string,
     *     data: array<string, mixed>,
     *     id: string|null,
     *     eventVersion: string|null,
     *     destination: string|null,
     *     routingKey: string|null
     * }
     */
    private array $values;

    /** @param array<string, mixed> $data */
    public function __construct(
        string $body,
        array $data = [],
        string|null $id = null,
        string|null $eventVersion = null,
        string|null $destination = null,
        string|null $routingKey = null,
    ) {
        $this->values = [
            'body' => $body,
            'data' => $data,
            'id' => $id,
            'eventVersion' => $eventVersion,
            'destination' => $destination,
            'routingKey' => $routingKey,
        ];
    }

    public function body(): string
    {
        return $this->values['body'];
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->values['data'];
    }

    public function id(): string|null
    {
        return $this->values['id'];
    }

    public function eventVersion(): string|null
    {
        return $this->values['eventVersion'];
    }

    public function destination(): string|null
    {
        return $this->values['destination'];
    }

    public function routingKey(): string|null
    {
        return $this->values['routingKey'];
    }
}
