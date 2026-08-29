<?php

declare(strict_types=1);

namespace Tests\Infrastructure\UseCase\ExampleApi;

use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExampleGroupsByType;
use Backendbase\Domain\ExampleBoundedContext\Contracts\Query\GetExamplesByGroup;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ReadModel\ExampleDetails;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ReadModel\ExampleListItem;
use Backendbase\Domain\ExampleBoundedContext\Contracts\ReadModel\ExamplePage;
use Backendbase\Domain\ExampleBoundedContext\Domain\ExampleType;
use Backendbase\Infrastructure\Configuration\ApplicationRuntimeSettings;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers\ExampleDetails as ExampleDetailsAction;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers\ExampleGroups;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers\Examples;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\Http\Actions\Action;
use Backendbase\Shared\Services\Settings;
use DateTimeImmutable;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class ExampleReadControllersTest extends TestCase
{
    #[Test]
    public function itReturnsExampleGroups(): void
    {
        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects(self::once())
            ->method('handle')
            ->with(self::callback(static function (GetExampleGroupsByType $query): bool {
                return $query->type() === ExampleType::USER && $query->typeTargetId() === 42;
            }))
            ->willReturn(['settings', 'preferences']);
        $action  = new ExampleGroups($queryBus, $this->createStub(LoggerInterface::class));
        $request = $this->request('/example-types/user/groups')
            ->withAttribute('typeSlug', 'user')
            ->withQueryParams(['typeTargetId' => 42, 'pageSize' => 10, 'page' => 2]);

        $payload = $this->payload($this->invoke($action, $request));

        self::assertSame(10, $payload['pageSize']);
        self::assertSame(2, $payload['page']);
        self::assertSame(2, $payload['total']);
        self::assertSame(['settings', 'preferences'], $payload['exampleGroups']);
    }

    #[Test]
    public function itReturnsAProjectedExamplePage(): void
    {
        $createdAt = new DateTimeImmutable('2026-08-25T10:00:00+00:00');
        $item      = new ExampleListItem(
            'example-id',
            'logo',
            'logo.png',
            ['heroImage' => 'hero.png', 'color' => 'blue'],
            true,
            $createdAt,
        );
        $queryBus  = $this->createMock(QueryBus::class);
        $queryBus->expects(self::once())
            ->method('handle')
            ->with(self::isInstanceOf(GetExamplesByGroup::class))
            ->willReturn(new ExamplePage([$item], 1));
        $settings = new ApplicationRuntimeSettings(new Settings(['cdnBaseUrl' => 'https://cdn.example.com/']));
        $action   = new Examples($queryBus, $settings, $this->createStub(LoggerInterface::class));
        $request  = $this->request('/example-types/system/groups/settings/examples')
            ->withAttribute('typeSlug', 'system')
            ->withAttribute('exampleGroup', 'settings')
            ->withQueryParams(['pageSize' => 20, 'page' => 1]);

        $payload = $this->payload($this->invoke($action, $request));
        $example = $payload['examples'][0];

        self::assertSame(1, $payload['total']);
        self::assertSame('example-id', $example['uuid']);
        self::assertSame('https://cdn.example.com/hero.png', $example['details']['heroImageUrl']);
        self::assertArrayNotHasKey('colorUrl', $example['details']);
        self::assertSame('2026-08-25T10:00:00+00:00', $example['createdAt']);
    }

    #[Test]
    public function itReturnsExampleDetails(): void
    {
        $updatedAt = new DateTimeImmutable('2026-08-25T11:00:00+00:00');
        $createdAt = new DateTimeImmutable('2026-08-25T10:00:00+00:00');
        $details   = new ExampleDetails(
            'example-id',
            ExampleType::SYSTEM,
            null,
            'settings',
            'page-size',
            '25',
            ['unit' => 'items'],
            true,
            $updatedAt,
            $createdAt,
        );
        $queryBus  = $this->createStub(QueryBus::class);
        $queryBus->method('handle')->willReturn($details);
        $action  = new ExampleDetailsAction($queryBus, $this->createStub(LoggerInterface::class));
        $request = $this->request('/example-types/system/groups/settings/examples/page-size')
            ->withAttribute('typeSlug', 'system')
            ->withAttribute('exampleGroup', 'settings')
            ->withAttribute('exampleKey', 'page-size');

        $payload = $this->payload($this->invoke($action, $request));

        self::assertSame('example-id', $payload['example']['uuid']);
        self::assertSame('25', $payload['example']['lookupValue']);
        self::assertSame('2026-08-25T11:00:00+00:00', $payload['example']['updatedAt']);
    }

    private function request(string $uri): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest('GET', $uri);
    }

    private function invoke(Action $action, ServerRequestInterface $request): ResponseInterface
    {
        return $action($request, new Response(), []);
    }

    /** @return array<string, mixed> */
    private function payload(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
