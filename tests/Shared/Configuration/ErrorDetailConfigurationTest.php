<?php

declare(strict_types=1);

namespace Tests\Shared\Configuration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_key_exists;

final class ErrorDetailConfigurationTest extends TestCase
{
    #[Test]
    public function itDisplaysHttpErrorDetailsOnlyInDevelopmentAndTests(): void
    {
        $key          = 'BACKENDBASE_ENV';
        $hadExisting  = array_key_exists($key, $_ENV);
        $existing     = $_ENV[$key] ?? null;
        $expectations = [
            'dev' => true,
            'development' => true,
            'local' => true,
            'test' => true,
            'ci' => false,
            'stage' => false,
            'production' => false,
            'unexpected' => false,
        ];

        try {
            foreach ($expectations as $environment => $expected) {
                $_ENV[$key] = $environment;
                $settings   = require 'config/autoload/global.php';

                self::assertSame($expected, $settings['displayErrorDetails'], $environment);
            }
        } finally {
            unset($_ENV[$key]);
            if ($hadExisting) {
                $_ENV[$key] = $existing;
            }
        }
    }
}
