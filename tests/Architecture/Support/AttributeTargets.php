<?php

declare(strict_types=1);

namespace Tests\Architecture\Support;

use ReflectionClass;

use function class_exists;
use function is_string;
use function str_contains;

final class AttributeTargets
{
    /**
     * @param class-string $attributeClass
     *
     * @return list<array{
     *     source: class-string,
     *     arguments: array<array-key, mixed>,
     *     target: string|null
     * }>
     */
    public static function forAttribute(string $attributeClass): array
    {
        $targets = [];
        foreach (ArchitectureDependencies::domain() as $file => $_dependencies) {
            if (str_contains($file, '/Tests/')) {
                continue;
            }

            $source = ArchitectureDependencies::className($file);
            if (! class_exists($source)) {
                continue;
            }

            $reflection = new ReflectionClass($source);
            $source     = $reflection->getName();
            foreach ($reflection->getAttributes($attributeClass) as $attribute) {
                $arguments = $attribute->getArguments();
                $target    = $arguments[0] ?? null;
                $targets[] = [
                    'source' => $source,
                    'arguments' => $arguments,
                    'target' => is_string($target) ? $target : null,
                ];
            }
        }

        return $targets;
    }
}
