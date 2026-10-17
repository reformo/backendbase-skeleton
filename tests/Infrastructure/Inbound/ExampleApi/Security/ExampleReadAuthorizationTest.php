<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\ExampleApi\Security;

use Backendbase\Domain\ExampleCatalog\Contracts\Query\GetEntryByCriteria;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryDetails;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryGroupPage;
use Backendbase\Domain\ExampleCatalog\Contracts\ReadModel\EntryPage;
use Backendbase\Domain\ExampleCatalog\Domain\EntryType;
use Backendbase\Domain\IdentityAndAccess\Contracts\TokenRegistry;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Shared\CQRS\QueryBus;
use DateTimeImmutable;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Infrastructure\Inbound\ExampleApi\ExampleApiFixture;
use Tests\Infrastructure\Inbound\IdentityApi\IdentityApiFixture;

final class ExampleReadAuthorizationTest extends TestCase
{
    #[DataProvider('protectedReads')]
    public function testUserTokensPermitProtectedReads(string $path, EntryGroupPage|EntryPage $page): void
    {
        $queries = $this->createMock(QueryBus::class);
        $queries->expects(self::once())->method('handle')->willReturn($page);
        $identity = ExampleApiFixture::identity([QueryBus::class => $queries]);
        IdentityScenario::registered($identity);
        $token = ExampleApiFixture::token($identity);
        $api   = IdentityApiFixture::example($identity);

        self::assertSame(200, $api->request('GET', $path, token: $token)->getStatusCode());
    }

    public function testDetailsRequireAnApiKeyWithoutRequiringOrCheckingBearerAuthentication(): void
    {
        $instant = new DateTimeImmutable('2026-10-17T10:00:00Z');
        $details = new EntryDetails('0199edbf-4e00-7000-8000-000000000001', EntryType::SYSTEM, null, 'settings', 'page-size', '25', [], true, $instant, $instant);
        $queries = $this->createMock(QueryBus::class);
        $queries->expects(self::exactly(2))->method('handle')->with(self::isInstanceOf(GetEntryByCriteria::class))->willReturn($details);
        $registry = $this->createMock(TokenRegistry::class);
        $registry->expects(self::never())->method('assertActive');
        $identity = ExampleApiFixture::identity([QueryBus::class => $queries, TokenRegistry::class => $registry]);
        $api      = IdentityApiFixture::example($identity);
        $path     = '/example-types/system/groups/settings/examples/page-size';

        self::assertSame(401, $api->handle(new ServerRequest(uri: $path, method: 'GET'))->getStatusCode());
        self::assertSame(200, $api->request('GET', $path)->getStatusCode());
        self::assertSame(200, $api->request('GET', $path, token: 'invalid')->getStatusCode());
    }

    /** @return iterable<string, array{string, EntryGroupPage|EntryPage}> */
    public static function protectedReads(): iterable
    {
        yield 'groups' => ['/example-types/system/groups', new EntryGroupPage([], 0)];
        yield 'examples' => ['/example-types/system/groups/settings/examples', new EntryPage([], 0)];
    }
}
