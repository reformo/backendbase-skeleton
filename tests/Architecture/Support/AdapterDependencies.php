<?php

declare(strict_types=1);

namespace Tests\Architecture\Support;

use function array_fill_keys;
use function array_keys;
use function preg_match;
use function str_ends_with;
use function str_starts_with;

final class AdapterDependencies
{
    /**
     * @param array<string, list<string>>|null $source
     *
     * @return array<string, list<string>>
     */
    public static function inbound(array|null $source = null): array
    {
        return ArchitectureDependencies::select(
            $source ?? ArchitectureDependencies::source(),
            static fn (string $file): bool => self::isInbound($file),
        );
    }

    /**
     * @param array<string, list<string>>|null $source
     *
     * @return array<string, list<string>>
     */
    public static function outbound(array|null $source = null): array
    {
        return ArchitectureDependencies::select(
            $source ?? ArchitectureDependencies::source(),
            static fn (string $file): bool => self::isOutbound($file),
        );
    }

    /**
     * @param array<string, list<string>> $source
     *
     * @return list<string>
     */
    public static function unclassified(array $source): array
    {
        return array_keys(ArchitectureDependencies::select(
            $source,
            static fn (string $file): bool => preg_match('#^src/Backendbase/(Domain/[^/]+|Infrastructure)/Adapters/#', $file) === 1
                && ! self::isInbound($file)
                && ! self::isOutbound($file)
                && $file !== 'src/Backendbase/Infrastructure/Adapters/Diagnostics/SafeExceptionContext.php',
        ));
    }

    /**
     * @param array<string, list<string>> $dependenciesByFile
     *
     * @return array<string, true>
     */
    public static function classSet(array $dependenciesByFile): array
    {
        $classes = [];
        foreach (array_keys($dependenciesByFile) as $file) {
            $classes[] = ArchitectureDependencies::className($file);
        }

        return array_fill_keys($classes, true);
    }

    private static function isInbound(string $file): bool
    {
        return str_starts_with($file, 'src/Backendbase/Infrastructure/Inbound/')
            || str_starts_with($file, 'src/Backendbase/Infrastructure/Adapters/Console/')
            || str_starts_with($file, 'src/Backendbase/Infrastructure/Adapters/Http/')
            || str_starts_with($file, 'src/Backendbase/Infrastructure/Adapters/Mapping/')
            || preg_match('#^src/Backendbase/Domain/[^/]+/Adapters/(Http|Console)/#', $file) === 1
            || str_ends_with($file, '/Adapters/Queue/ExternalIntegrationEventMessageProcessor.php')
            || str_ends_with($file, '/Adapters/Queue/ExternalIntegrationEventDispatcher.php');
    }

    private static function isOutbound(string $file): bool
    {
        if (self::isInbound($file)) {
            return false;
        }

        return preg_match('#^src/Backendbase/Domain/[^/]+/Adapters/(Authentication|Persistence|Notification|ReadModel)/#', $file) === 1
            || $file === 'src/Backendbase/Infrastructure/Adapters/S3Bucket.php'
            || preg_match('#^src/Backendbase/Infrastructure/Adapters/(Aws|CQRS|DomainEvents|EventManager|Notification|Persistence|Queue|Security|Time)/#', $file) === 1;
    }
}
