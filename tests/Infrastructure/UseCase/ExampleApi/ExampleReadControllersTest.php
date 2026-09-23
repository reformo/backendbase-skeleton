<?php

declare(strict_types=1);

namespace Tests\Infrastructure\UseCase\ExampleApi;

use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntriesByGroup;
use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryGroupsByType;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryDetails;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryGroupPage;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryListItem;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryPage;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
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
            ->with(self::callback(static function (GetEntryGroupsByType $query): bool {
                $pagination = $query->pagination();

                return $query->type() === EntryType::USER && $query->typeTargetId() === 42
                    && $pagination->pageSize() === 1 && $pagination->page() === 2;
            }))
            ->willReturn(new EntryGroupPage(['preferences'], 2));
        $action  = new ExampleGroups($queryBus, $this->createStub(LoggerInterface::class));
        $request = $this->request('/example-types/user/groups')
            ->withAttribute('type-slug', 'user')
            ->withQueryParams(['typeTargetId' => 42, 'pageSize' => 1, 'page' => 2]);

        $payload = $this->payload($this->invoke($action, $request));

        self::assertSame(1, $payload['pageSize']);
        self::assertSame(2, $payload['page']);
        self::assertSame(2, $payload['total']);
        self::assertSame(['preferences'], $payload['exampleGroups']);
    }

    #[Test]
    public function itReturnsAProjectedExamplePage(): void
    {
        $createdAt = new DateTimeImmutable('2026-08-25T10:00:00+00:00');
        $item      = new EntryListItem(
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
            ->with(self::isInstanceOf(GetEntriesByGroup::class))
            ->willReturn(new EntryPage([$item], 1));
        $settings = new ApplicationRuntimeSettings(new Settings(['cdnBaseUrl' => 'https://cdn.example.com/']));
        $action   = new Examples($queryBus, $settings, $this->createStub(LoggerInterface::class));
        $request  = $this->request('/example-types/system/groups/settings/examples')
            ->withAttribute('type-slug', 'system')
            ->withAttribute('example-group', 'settings')
            ->withQueryParams(['pageSize' => 20, 'page' => 1]);

        $payload = $this->payload($this->invoke($action, $request));
        $entry   = $payload['examples'][0];

        self::assertSame(1, $payload['total']);
        self::assertSame('example-id', $entry['uuid']);
        self::assertSame('https://cdn.example.com/hero.png', $entry['details']['heroImageUrl']);
        self::assertArrayNotHasKey('colorUrl', $entry['details']);
        self::assertSame('2026-08-25T10:00:00+00:00', $entry['createdAt']);
    }

    #[Test]
    public function itReturnsExampleDetails(): void
    {
        $updatedAt = new DateTimeImmutable('2026-08-25T11:00:00+00:00');
        $createdAt = new DateTimeImmutable('2026-08-25T10:00:00+00:00');
        $details   = new EntryDetails(
            'example-id',
            EntryType::SYSTEM,
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
            ->withAttribute('type-slug', 'system')
            ->withAttribute('example-group', 'settings')
            ->withAttribute('example-key', 'page-size');

        $payload = $this->payload($this->invoke($action, $request));

        self::assertSame('example-id', $payload['example']['uuid']);
        self::assertArrayHasKey('typeTargetId', $payload['example']);
        self::assertNull($payload['example']['typeTargetId']);
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
