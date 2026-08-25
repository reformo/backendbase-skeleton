<?php

declare(strict_types=1);

namespace Tests\Infrastructure\UseCase\ExampleApi;

use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\ExampleRequestInput;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers\ExampleGroups;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\Exception\InvalidUserInput;
use Laminas\Diactoros\ServerRequestFactory;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Factory\AppFactory;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class ExampleInputValidationTest extends TestCase
{
    #[Test]
    public function itReturnsBadRequestForAnInvalidExampleType(): void
    {
        $response = $this->request('invalid', []);

        self::assertInvalidInputResponse($response);
    }

    #[Test]
    public function itReturnsBadRequestForAnInvalidTypeTargetId(): void
    {
        $response = $this->request('system', ['typeTargetId' => 'not-an-integer']);

        self::assertInvalidInputResponse($response);
    }

    #[Test]
    public function itRejectsANonScalarPositiveInteger(): void
    {
        $this->expectException(InvalidUserInput::class);

        ExampleRequestInput::positiveInteger([], 'page');
    }

    /** @param array<string, mixed> $query */
    private function request(string $type, array $query): ResponseInterface
    {
        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects(self::never())->method('handle');
        $app = AppFactory::create();
        $app->get('/examples/{typeSlug}', new ExampleGroups($queryBus, new Logger('input-test'), null));
        $app->addRoutingMiddleware();
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/examples/' . $type)
            ->withQueryParams($query);

        return $app->handle($request);
    }

    private static function assertInvalidInputResponse(ResponseInterface $response): void
    {
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('general/invalid-user-input', $payload['code']);
    }
}
