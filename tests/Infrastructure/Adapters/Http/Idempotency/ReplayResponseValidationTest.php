<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Adapters\Http\Idempotency;

use Backendbase\Infrastructure\Adapters\Http\Idempotency\ReplayInput;
use Backendbase\Infrastructure\Adapters\Http\Idempotency\ReplayResponse;
use Backendbase\Infrastructure\Adapters\Security\Idempotency\SodiumRequestReplayCipher;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ResponseFactory;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Slim\App;
use Tests\Support\Http\ReplayFixture;
use Tests\Support\Http\ReplayMiddlewareFixture;

use function json_encode;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

final class ReplayResponseValidationTest extends TestCase
{
    /** @param array<string, mixed> $values */
    #[DataProvider('invalidResponses')]
    public function testInvalidStoredResponseCannotBeReplayed(array $values): void
    {
        $this->expectException(RuntimeException::class);
        ReplayResponse::decode(json_encode($values, JSON_THROW_ON_ERROR));
    }

    public function testOversizedResponsesAreNotStored(): void
    {
        $response = new Response();
        $body     = $response->getBody();
        $body->write(str_repeat('x', 1048577));
        $this->expectException(RuntimeException::class);
        ReplayResponse::encode($response);
    }

    public function testUnnamedOperationsCannotShareAnImplicitScope(): void
    {
        $cipher = new SodiumRequestReplayCipher(ReplayFixture::settings());
        $app    = new App(new ResponseFactory());
        $app->post('/write/one', static function (ServerRequestInterface $request) use ($cipher): Response {
            ReplayInput::identity($request, 'key', $cipher);

            return new Response();
        });
        $app->addRoutingMiddleware();
        $this->expectException(LogicException::class);
        $app->handle(ReplayMiddlewareFixture::request());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidResponses(): iterable
    {
        $valid = ['status' => 200, 'headers' => [], 'body' => ''];

        yield 'missing fields' => [[]];
        yield 'status type' => [[...$valid, 'status' => '200']];
        yield 'status range' => [[...$valid, 'status' => 199]];
        yield 'oversized body' => [[...$valid, 'body' => str_repeat('x', 1048577)]];
        yield 'empty header' => [[...$valid, 'headers' => ['' => ['value']]]];
        yield 'numeric header' => [[...$valid, 'headers' => [1 => ['value']]]];
        yield 'header collection type' => [[...$valid, 'headers' => ['Content-Type' => 'application/json']]];
        yield 'header list' => [[...$valid, 'headers' => ['Content-Type' => ['name' => 'value']]]];
        yield 'header value type' => [[...$valid, 'headers' => ['Content-Type' => [42]]]];
    }
}
