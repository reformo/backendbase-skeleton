<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Architecture\Support\BoundedContextDependencies;

final class BoundedContextIsolationTest extends TestCase
{
    #[Test]
    public function boundedContextsDoNotDependOnOtherBoundedContexts(): void
    {
        self::assertSame([], BoundedContextDependencies::isolationViolations());
    }
}
