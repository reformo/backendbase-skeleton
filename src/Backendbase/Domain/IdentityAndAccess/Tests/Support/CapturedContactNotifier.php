<?php

declare(strict_types=1);

namespace Backendbase\Domain\IdentityAndAccess\Tests\Support;

use Backendbase\Domain\IdentityAndAccess\Contracts\ContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;

final class CapturedContactNotifier implements ContactNotifier
{
    /** @var array<string, string> */
    private array $codes = [];
    /** @var list<array{generationId: string, type: string, value: string}> */
    private array $deliveries = [];

    public function send(string $generationId, ContactValue $contact, string $code): void
    {
        $this->codes[$generationId] = $code;
        $this->deliveries[]         = ['generationId' => $generationId, 'type' => $contact->type(), 'value' => $contact->value()];
    }

    public function code(string $generationId): string
    {
        return $this->codes[$generationId];
    }

    /** @return list<array{generationId: string, type: string, value: string}> */
    public function deliveries(): array
    {
        return $this->deliveries;
    }
}
