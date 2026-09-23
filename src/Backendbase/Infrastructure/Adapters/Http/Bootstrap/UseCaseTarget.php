<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http\Bootstrap;

final readonly class UseCaseTarget
{
    private const array TARGETS = [
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

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }
}
