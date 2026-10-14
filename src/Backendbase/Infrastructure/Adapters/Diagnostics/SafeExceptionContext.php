<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Diagnostics;

use Throwable;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class SafeExceptionContext
{
    /** @return array{exception: class-string<Throwable>, file: string, line: int, trace: string} */
    public static function from(Throwable $exception): array
    {
        return [
            'exception' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => json_encode(self::frames($exception), JSON_THROW_ON_ERROR),
        ];
    }

    /** @return list<array<string, int|string>> */
    public static function frames(Throwable $exception): array
    {
        $frames = [];
        foreach ($exception->getTrace() as $frame) {
            $frames[] = [
                'function' => $frame['function'],
                'file' => $frame['file'] ?? 'internal',
                'line' => $frame['line'] ?? 0,
                'class' => $frame['class'] ?? '',
                'type' => $frame['type'] ?? '',
            ];
        }

        return $frames;
    }
}
