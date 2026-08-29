<?php

declare(strict_types=1);

namespace Tests\Infrastructure\UseCase\ExampleApi;

use Backendbase\Domain\IdentityAndAccess\Authorization\Acl;
use Backendbase\Domain\IdentityAndAccess\Exception\AuthorizationExpired;
use Backendbase\Infrastructure\Adapters\Http\DomainErrorProblemDetailsMapper;
use Backendbase\Infrastructure\Adapters\Http\HttpErrorHandler;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\ExampleRequestInput;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers\ChangeExampleDetails;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers\ExampleGroups;
use Backendbase\Shared\Authorization\AccessControl;
use Backendbase\Shared\CQRS\CommandBus;
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

    #[Test]
    public function itRejectsAMissingAuthorizationContext(): void
    {
        $this->expectException(AuthorizationExpired::class);

        ExampleRequestInput::accessControl(null);
    }

    #[Test]
    public function itReturnsBadRequestForInvalidPatchFieldTypes(): void
    {
        $cases = [
            [['lookupValue' => []], 'The lookupValue value must be a string.'],
            [['details' => 'invalid'], 'The details value must be an object.'],
            [['isActive' => 'true'], 'The isActive value must be a boolean.'],
        ];

        foreach ($cases as [$payload, $detail]) {
            self::assertInvalidInputResponse($this->patchRequest($payload), $detail);
        }
    }

    /** @param array<string, mixed> $query */
    private function request(string $type, array $query): ResponseInterface
    {
        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects(self::never())->method('handle');
        $logger = new Logger('input-test');
        $app    = AppFactory::create();
        $app->get('/examples/{typeSlug}', new ExampleGroups($queryBus, $logger));
        $app->addRoutingMiddleware();
        $errorMiddleware = $app->addErrorMiddleware(false, false, false);
        $errorMiddleware->setDefaultErrorHandler(new HttpErrorHandler(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            $logger,
            new DomainErrorProblemDetailsMapper(),
        ));
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/examples/' . $type)
            ->withQueryParams($query);

        return $app->handle($request);
    }

    /** @param array<string, mixed> $payload */
    private function patchRequest(array $payload): ResponseInterface
    {
        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->expects(self::never())->method('handle');
        $logger = new Logger('patch-input-test');
        $app    = AppFactory::create();
        $app->patch(
            '/examples/{typeSlug}/{exampleGroup}/{exampleKey}',
            new ChangeExampleDetails($commandBus, $logger),
        );
        $app->addRoutingMiddleware();
        $errorMiddleware = $app->addErrorMiddleware(false, false, false);
        $errorMiddleware->setDefaultErrorHandler(new HttpErrorHandler(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            $logger,
            new DomainErrorProblemDetailsMapper(),
        ));
        $request = (new ServerRequestFactory())
            ->createServerRequest('PATCH', '/examples/system/settings/page-size')
            ->withAttribute(AccessControl::class, new Acl(['full-privileges']))
            ->withParsedBody($payload);

        return $app->handle($request);
    }

    private static function assertInvalidInputResponse(
        ResponseInterface $response,
        string|null $expectedDetail = null,
    ): void {
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        self::assertSame('general/invalid-user-input', $payload['code']);
        if ($expectedDetail === null) {
            return;
        }

        self::assertSame($expectedDetail, $payload['detail']);
    }
}
