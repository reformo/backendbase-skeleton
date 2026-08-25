<?php

declare(strict_types=1);

namespace Backendbase\Shared\Options\System;

enum Environment: string
{
    case PRODUCTION = 'production';
    case STAGE      = 'stage';
    case CI         = 'ci';
    case TEST       = 'test';
    case DEV        = 'dev';

    public static function fromValue(string $value): Environment
    {
        return match ($value) {
            self::STAGE->value => self::STAGE,
            self::CI->value => self::CI,
            self::TEST->value => self::TEST,
            'dev', 'development', 'local' => self::DEV,
            default => self::PRODUCTION,
        };
    }
}
