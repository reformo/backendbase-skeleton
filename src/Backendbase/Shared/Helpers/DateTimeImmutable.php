<?php

declare(strict_types=1);

namespace Backendbase\Shared\Helpers;

use DateTimeZone;

class DateTimeImmutable extends \DateTimeImmutable
{
    public static function create(string|null $date = 'now', string|null $tz = 'UTC'): self
    {
        $date ??= 'now';
        $tz   ??= 'UTC';

        return new self($date, new DateTimeZone($tz));
    }
}
