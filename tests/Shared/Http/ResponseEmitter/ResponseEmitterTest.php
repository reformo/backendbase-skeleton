<?php

declare(strict_types=1);

namespace Tests\Shared\Http\ResponseEmitter;

use Backendbase\Shared\Http\ResponseEmitter\ResponseEmitter;
use Backendbase\Shared\Services\Settings;
use Laminas\Diactoros\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function ob_get_clean;
use function ob_start;

final class ResponseEmitterTest extends TestCase
{
    #[Test]
    public function itEmitsTheResponseForAnAllowedOriginAndClearsExistingOutput(): void
    {
        $originalOrigin         = $_SERVER['HTTP_ORIGIN'] ?? null;
        $_SERVER['HTTP_ORIGIN'] = 'https://app.example.com';
        $response               = new Response();
        $response->getBody()->write('response-body');
        $emitter = new ResponseEmitter(new Settings([
            'headers' => [
                'Access-Control-Allow-Origin' => 'https://other.example.com, https://app.example.com',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
            ],
        ]));

        ob_start();
        echo 'stale-output';
        $emitter->emit($response);
        $output = ob_get_clean();

        if ($originalOrigin === null) {
            unset($_SERVER['HTTP_ORIGIN']);
        } else {
            $_SERVER['HTTP_ORIGIN'] = $originalOrigin;
        }

        self::assertSame('response-body', $output);
    }

    #[Test]
    public function itUsesTheFallbackOrigin(): void
    {
        $originalOrigin = $_SERVER['HTTP_ORIGIN'] ?? null;
        unset($_SERVER['HTTP_ORIGIN']);
        $response = new Response();
        $response->getBody()->write('fallback-body');
        $emitter = new ResponseEmitter(new Settings([
            'headers' => [
                'Access-Control-Allow-Origin' => 'https://app.example.com',
                'Access-Control-Allow-Headers' => 'Content-Type',
            ],
        ]));

        ob_start();
        $emitter->emit($response);
        $output = ob_get_clean();

        if ($originalOrigin !== null) {
            $_SERVER['HTTP_ORIGIN'] = $originalOrigin;
        }

        self::assertSame('fallback-body', $output);
    }
}
