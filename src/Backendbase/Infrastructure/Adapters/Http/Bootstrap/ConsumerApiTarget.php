<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Bootstrap;

use function is_string;
use function str_starts_with;

final readonly class ConsumerApiTarget
{
    private const array TARGETS = [
        'user' => ['name' => 'UserApi', 'slug' => 'user-api'],
        'system-admin' => ['name' => 'SystemAdminApi', 'slug' => 'system-admin-api'],
        'example' => [
            'name' => 'ExampleApi',
            'slug' => 'example-api',
        ],
    ];

    private function __construct(private string $name, private string $slug)
    {
    }

    public static function fromSourceId(string|null $sourceId): self|null
    {
        if ($sourceId === null) {
            return null;
        }

        $target = self::TARGETS[$sourceId] ?? null;
        if ($target === null) {
            return null;
        }

        return new self($target['name'], $target['slug']);
    }

    /** @param array<string, mixed> $server */
    public static function fromRequest(array $server): self|null
    {
        $source = $server['HTTP_X_SOURCE_ID'] ?? null;
        if ($source !== null) {
            return is_string($source) ? self::fromSourceId($source) : null;
        }

        $uri = $server['REQUEST_URI'] ?? null;
        if (($server['REQUEST_METHOD'] ?? null) !== 'OPTIONS' || ! is_string($uri)) {
            return null;
        }

        foreach (self::TARGETS as $target) {
            if (str_starts_with($uri, '/' . $target['slug'] . '/')) {
                return new self($target['name'], $target['slug']);
            }
        }

        return null;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }
}
