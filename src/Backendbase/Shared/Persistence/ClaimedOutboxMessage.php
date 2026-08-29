<?php

declare(strict_types=1);

namespace Backendbase\Shared\Persistence;

final readonly class ClaimedOutboxMessage
{
    /**
     * @var array{
     *     id: string,
     *     eventName: string,
     *     eventVersion: string,
     *     payload: string,
     *     attempts: int,
     *     claimToken: string
     * }
     */
    private array $data;

    public function __construct(
        string $id,
        string $eventName,
        string $eventVersion,
        string $payload,
        int $attempts,
        string $claimToken,
    ) {
        $this->data = [
            'id' => $id,
            'eventName' => $eventName,
            'eventVersion' => $eventVersion,
            'payload' => $payload,
            'attempts' => $attempts,
            'claimToken' => $claimToken,
        ];
    }

    public function id(): string
    {
        return $this->data['id'];
    }

    public function eventName(): string
    {
        return $this->data['eventName'];
    }

    public function eventVersion(): string
    {
        return $this->data['eventVersion'];
    }

    public function payload(): string
    {
        return $this->data['payload'];
    }

    public function attempts(): int
    {
        return $this->data['attempts'];
    }

    public function claimToken(): string
    {
        return $this->data['claimToken'];
    }
}
