<?php

declare(strict_types=1);

namespace Backendbase\Shared\Helpers;

use DateTimeZone;

class DateTimeImmutable extends \DateTimeImmutable
{
    public static function create(string|null $date = 'now', string|null $timezone = 'UTC'): self
    {
        $date     ??= 'now';
        $timezone ??= 'UTC';

        return new self($date, new DateTimeZone($timezone));
    }
}
