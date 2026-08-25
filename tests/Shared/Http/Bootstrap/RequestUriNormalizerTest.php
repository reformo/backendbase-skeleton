<?php

declare(strict_types=1);

namespace Tests\Shared\Http\Bootstrap;

use Backendbase\Shared\Http\Bootstrap\RequestUriNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestUriNormalizerTest extends TestCase
{
    #[Test]
    public function itRemovesTheScriptDirectoryFromTheRequestUri(): void
    {
        $server = RequestUriNormalizer::normalize([
            'SCRIPT_NAME' => '/example-api/index.php',
            'REQUEST_URI' => '/example-api/examples',
        ]);

        self::assertSame('/examples', $server['REQUEST_URI']);
    }

    #[Test]
    public function itKeepsTheRootRequestUriUnchanged(): void
    {
        $server = RequestUriNormalizer::normalize([
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/examples',
        ]);

        self::assertSame('/examples', $server['REQUEST_URI']);
    }
}
