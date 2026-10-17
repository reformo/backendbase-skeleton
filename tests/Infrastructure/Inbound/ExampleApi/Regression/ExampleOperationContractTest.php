<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\ExampleApi\Regression;

use Backendbase\Domain\ExampleCatalog\Contracts\Command\RemoveEntry;
use Backendbase\Domain\ExampleCatalog\Domain\Exception\EntryAlreadyExists;
use Backendbase\Infrastructure\Adapters\Http\DomainErrorProblemDetailsMapper;
use Backendbase\Infrastructure\Adapters\Http\HttpErrorHandler;
use Backendbase\Infrastructure\Inbound\ExampleApi\Controllers\Example\ExampleRequestInput;
use Backendbase\Shared\CQRS\CommandBus;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Yaml\Yaml;
use Tests\Infrastructure\Inbound\ExampleApi\ExampleApiFixture;
use Tests\Support\Http\ApiResponseAssertions;
use Tests\Support\Http\JsonRequestBody;

use function array_column;
use function glob;

final class ExampleOperationContractTest extends TestCase
{
    #[Test]
    public function itAcceptsEveryDocumentedType(): void
    {
        foreach (glob('resources/api-docs/example-api/example/*.yaml') ?: [] as $file) {
            $this->assertOperationTypes(Yaml::parseFile($file));
        }
    }

    #[Test]
    public function itUsesTheDocumentedDeleteTargetParameter(): void
    {
        $definition = Yaml::parseFile('resources/api-docs/example-api/example/example-details.yaml');
        $parameters = array_column($definition['delete']['parameters'], null, 'name');
        self::assertSame('query', $parameters['typeTargetId']['in']);
        $commands = $this->createMock(CommandBus::class);
        $commands->expects(self::once())->method('handle')->with(self::callback(static function (RemoveEntry $command): bool {
            $identity = $command->identity();

            return $identity->typeTargetId() === 42;
        }));
        [$app, $token] = ExampleApiFixture::authenticated($commands);
        $request       = new ServerRequestFactory()->createServerRequest('DELETE', '/example-types/user/groups/settings/examples/key');
        $request       = $request->withHeader('Backendbase-Api-Key', 'example-api-key');
        $request       = $request->withHeader('Authorization', 'Bearer ' . $token);
        $request       = $request->withQueryParams(['typeTargetId' => '42']);
        $response      = $app->handle($request);
        self::assertSame(204, $response->getStatusCode());
    }

    #[Test]
    public function itDocumentsTheActualDuplicateEntryResponse(): void
    {
        $commands = $this->createMock(CommandBus::class);
        $commands->expects(self::once())->method('handle')->willThrowException(EntryAlreadyExists::create('The example already exists.'));
        [$app, $token]   = ExampleApiFixture::authenticated($commands);
        $errorMiddleware = $app->addErrorMiddleware(false, false, false);
        $errorMiddleware->setDefaultErrorHandler(new HttpErrorHandler($app->getCallableResolver(), $app->getResponseFactory(), new NullLogger(), new DomainErrorProblemDetailsMapper()));
        $request  = new ServerRequestFactory()->createServerRequest('POST', '/example-types/system/groups/settings/examples');
        $request  = $request->withHeader('Backendbase-Api-Key', 'example-api-key');
        $request  = $request->withHeader('Authorization', 'Bearer ' . $token);
        $request  = $request->withBody(JsonRequestBody::create(['lookupValue' => 'value']));
        $response = $app->handle($request);
        self::assertSame(409, $response->getStatusCode());
        ApiResponseAssertions::assertMatches('example-api', 'POST', '/example-types/{type-slug}/groups/{example-group}/examples', $response);
    }

    #[Test]
    public function itDocumentsValidationErrorsWithoutDispatchingInvalidUpdates(): void
    {
        $commands = $this->createMock(CommandBus::class);
        $commands->expects(self::never())->method('handle');
        [$app, $token]   = ExampleApiFixture::authenticated($commands);
        $errorMiddleware = $app->addErrorMiddleware(false, false, false);
        $errorMiddleware->setDefaultErrorHandler(new HttpErrorHandler($app->getCallableResolver(), $app->getResponseFactory(), new NullLogger(), new DomainErrorProblemDetailsMapper()));
        $request  = new ServerRequestFactory()->createServerRequest('PATCH', '/example-types/system/groups/settings/examples/key');
        $request  = $request->withHeader('Backendbase-Api-Key', 'example-api-key');
        $request  = $request->withHeader('Authorization', 'Bearer ' . $token);
        $request  = $request->withBody(JsonRequestBody::create(['details' => ['x']]));
        $response = $app->handle($request);
        self::assertSame(400, $response->getStatusCode());
        ApiResponseAssertions::assertMatches('example-api', 'PATCH', '/example-types/{type-slug}/groups/{example-group}/examples/{example-key}', $response);
    }

    /** @param array<string, mixed> $operations */
    private function assertOperationTypes(array $operations): void
    {
        foreach ($operations as $operation) {
            $parameters = array_column($operation['parameters'], null, 'name');
            $this->assertTypes($parameters['type-slug']['schema']['enum']);
        }
    }

    /** @param list<string> $types */
    private function assertTypes(array $types): void
    {
        foreach ($types as $type) {
            $accepted = ExampleRequestInput::type($type);
            self::assertSame($type, $accepted->value);
        }
    }
}
