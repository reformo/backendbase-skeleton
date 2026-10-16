<?php

declare(strict_types=1);

namespace Tests\Security\Http;

use Backendbase\Domain\IdentityAndAccess\Contracts\ContactNotifier;
use Backendbase\Domain\IdentityAndAccess\Domain\Contacts\ContactValue;
use RuntimeException;

use function file_put_contents;
use function hash;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const LOCK_EX;

final readonly class FileContactNotifier implements ContactNotifier
{
    public function __construct(private string $directory)
    {
    }

    public function send(string $generationId, ContactValue $contact, string $code): void
    {
        $value = $contact->value();
        $path  = $this->directory . '/' . hash('sha256', $value) . '.json';
        if (file_put_contents($path, json_encode(['code' => $code], JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException('The isolated contact sink is unavailable.');
        }
    }
}
