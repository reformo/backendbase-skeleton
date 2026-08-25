<?php

declare(strict_types=1);

namespace Tests\Shared\Primitives\Notification;

final class UnreadableFileStream
{
    public mixed $context;

    public function stream_open(string $path, string $mode, int $options, string|null &$openedPath): bool
    {
        return false;
    }

    /** @return array<int|string, int> */
    public function url_stat(string $path, int $flags): array
    {
        return [2 => 0100644, 7 => 1, 'mode' => 0100644, 'size' => 1];
    }
}
