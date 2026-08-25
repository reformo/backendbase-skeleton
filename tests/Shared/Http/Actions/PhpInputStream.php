<?php

declare(strict_types=1);

namespace Tests\Shared\Http\Actions;

use function strlen;
use function substr;

final class PhpInputStream
{
    public mixed $context;
    public static string|false $body = '';
    private int $position            = 0;

    public function stream_open(string $path, string $mode, int $options, string|null &$openedPath): bool
    {
        return self::$body !== false;
    }

    public function stream_read(int $count): string
    {
        $body            = (string) self::$body;
        $value           = substr($body, $this->position, $count);
        $this->position += strlen($value);

        return $value;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen((string) self::$body);
    }

    /** @return array<string, int> */
    public function stream_stat(): array
    {
        return [];
    }
}
