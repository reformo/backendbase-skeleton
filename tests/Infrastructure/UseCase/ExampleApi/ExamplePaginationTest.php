<?php

declare(strict_types=1);

namespace Tests\Infrastructure\UseCase\ExampleApi;

use Backendbase\Infrastructure\Configuration\ApplicationRuntimeSettings;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers\ExampleGroups;
use Backendbase\Infrastructure\UseCase\ExampleApi\Controllers\Example\Handlers\Examples;
use Backendbase\Shared\CQRS\QueryBus;
use Backendbase\Shared\Exception\InvalidUserInput;
use Backendbase\Shared\Services\Settings;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

use const PHP_INT_MAX;

final class ExamplePaginationTest extends TestCase
{
    /** @return iterable<string, array{bool}> */
    public static function collectionEndpoints(): iterable
    {
        yield 'groups' => [true];
        yield 'examples' => [false];
    }

    #[Test]
    #[DataProvider('collectionEndpoints')]
    public function itRejectsAnOverflowingOffsetBeforeQueryDispatch(bool $groups): void
    {
        $queryBus = $this->createMock(QueryBus::class);
        $queryBus->expects(self::never())->method('handle');
        $settings = new ApplicationRuntimeSettings(new Settings(['cdnBaseUrl' => '']));
        $action   = $groups
            ? new ExampleGroups($queryBus, new NullLogger())
            : new Examples($queryBus, $settings, new NullLogger());
        $request  = (new ServerRequestFactory())->createServerRequest('GET', '/example-types/system/groups');
        $request  = $request->withAttribute('type-slug', 'system');
        $request  = $request->withAttribute('example-group', 'settings');
        $request  = $request->withQueryParams(['pageSize' => '1000', 'page' => (string) PHP_INT_MAX]);

        $this->expectException(InvalidUserInput::class);

        $action($request, new Response(), []);
    }
}
