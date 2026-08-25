<?php

declare(strict_types=1);

namespace Backendbase\Shared\Health;

use Throwable;

final readonly class ReadinessChecks
{
    /** @param list<ReadinessCheck> $checks */
    public function __construct(private array $checks)
    {
    }

    public function run(): ReadinessReport
    {
        $results = [];
        foreach ($this->checks as $check) {
            $results[] = self::runCheck($check);
        }

        return new ReadinessReport($results);
    }

    private static function runCheck(ReadinessCheck $check): ReadinessResult
    {
        try {
            $check->check();
        } catch (Throwable) {
            return ReadinessResult::unavailable($check->name());
        }

        return ReadinessResult::ready($check->name());
    }
}
