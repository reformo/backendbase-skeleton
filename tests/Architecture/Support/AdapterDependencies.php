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
    /** @return array<string, list<string>> */
    public static function inbound(): array
    {
        return ArchitectureDependencies::select(
            ArchitectureDependencies::source(),
            static fn (string $file): bool => self::isInbound($file),
        );
    }

    /** @return array<string, list<string>> */
    public static function outbound(): array
    {
        return ArchitectureDependencies::select(
            ArchitectureDependencies::source(),
            static fn (string $file): bool => self::isOutbound($file),
        );
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
        return str_starts_with($file, 'src/Backendbase/Infrastructure/UseCase/')
            || preg_match('#^src/Backendbase/Domain/[^/]+/Adapters/Http/#', $file) === 1
            || str_ends_with($file, '/Adapters/Queue/ExternalIntegrationEventMessageProcessor.php')
            || str_ends_with($file, '/Adapters/Queue/NotificationMessageProcessor.php')
            || str_ends_with($file, '/Adapters/Queue/ExternalIntegrationEventDispatcher.php');
    }

    private static function isOutbound(string $file): bool
    {
        if (self::isInbound($file)) {
            return false;
        }

        return preg_match('#^src/Backendbase/Domain/[^/]+/Adapters/(Authentication|Persistence)/#', $file) === 1
            || preg_match('#^src/Backendbase/Infrastructure/Adapters/(Aws|Notification|Persistence|Queue)/#', $file) === 1;
    }
}
