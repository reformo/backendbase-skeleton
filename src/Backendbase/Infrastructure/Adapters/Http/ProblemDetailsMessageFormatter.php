<?php

declare(strict_types=1);

namespace Backendbase\Infrastructure\Adapters\Http;

use Backendbase\Shared\Services\Translator;

use function count;
use function is_array;
use function preg_match_all;
use function str_contains;
use function str_replace;

final class ProblemDetailsMessageFormatter
{
    /**
     * @param array<string, mixed> $payload
     *
     * @return string|array<array-key, mixed>
     */
    public static function format(string $message, array $payload, Translator|null $translator): string|array
    {
        $message = self::translate($message, $payload, $translator);
        if (is_array($message)) {
            return $message;
        }

        preg_match_all('/:([a-zA-Z0-9])+/i', $message, $matches);
        if (count($matches[0]) === 0) {
            return $message;
        }

        $replaceValues = [];
        foreach ($matches[0] as $match) {
            $replaceValues[] = $payload[str_replace(':', '', $match)] ?? '';
        }

        return str_replace($matches[0], $replaceValues, $message);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return string|array<array-key, mixed>
     */
    private static function translate(string $message, array $payload, Translator|null $translator): string|array
    {
        if ($translator === null || str_contains($message, ' ')) {
            return $message;
        }

        return $translator->translate($message, $payload);
    }
}
