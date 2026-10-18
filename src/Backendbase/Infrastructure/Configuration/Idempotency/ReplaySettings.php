<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Configuration\Idempotency;

use Backendbase\Shared\Settings;
use InvalidArgumentException;

use function base64_decode;
use function is_array;
use function is_string;
use function strlen;

final readonly class ReplaySettings
{
    public function __construct(private Settings $settings)
    {
    }

    public function key(): string
    {
        $settings = $this->settings;
        $values   = $settings->get();
        $section  = is_array($values) ? ($values['idempotency'] ?? null) : null;
        $encoded  = is_array($section) ? ($section['key'] ?? null) : null;
        $key      = is_string($encoded) ? base64_decode($encoded, true) : false;
        if ($key === false || strlen($key) !== 32) {
            throw new InvalidArgumentException('BACKENDBASE_IDEMPOTENCY_KEY must contain a base64-encoded 32-byte secret.');
        }

        return $key;
    }
}
