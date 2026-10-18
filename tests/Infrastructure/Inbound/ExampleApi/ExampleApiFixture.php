<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Inbound\ExampleApi;

use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationClock;
use Backendbase\Domain\IdentityAndAccess\Application\AuthenticationGate;
use Backendbase\Domain\IdentityAndAccess\Application\SessionIssuance;
use Backendbase\Domain\IdentityAndAccess\Domain\Identity\Realm;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityFixture;
use Backendbase\Domain\IdentityAndAccess\Tests\Support\IdentityScenario;
use Backendbase\Infrastructure\Adapters\Http\Idempotency\Idempotency;
use Backendbase\Shared\CQRS\CommandBus;
use Backendbase\Shared\Services\Settings as SettingsValue;
use Backendbase\Shared\Settings;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Slim\App;
use Slim\Factory\AppFactory;

final class ExampleApiFixture
{
    /** @return App<ContainerInterface|null> */
    public static function build(CommandBus $commandBus, IdentityFixture|null $identity = null): App
    {
        $identity ??= self::identity();
        $container = $identity->container();
        $builder   = new ContainerBuilder();
        $builder->addDefinitions([
            Settings::class => new SettingsValue([
                'example-api' => ['api-key' => 'example-api-key'],
                'cdnBaseUrl' => '',
            ]),
            LoggerInterface::class => new NullLogger(),
            CommandBus::class => $commandBus,
            AuthenticationGate::class => $container->get(AuthenticationGate::class),
            Idempotency::class => $container->get(Idempotency::class),
        ]);
        AppFactory::setContainer($builder->build());
        $app        = AppFactory::create();
        $directory  = 'src/Backendbase/Infrastructure/Inbound/ExampleApi';
        $middleware = require $directory . '/middleware.php';
        $routes     = require $directory . '/routes.php';
        $middleware($app);
        $routes($app);
        $app->addRoutingMiddleware();

        return $app;
    }

    /** @return array{App<ContainerInterface|null>, string} */
    public static function authenticated(CommandBus $commandBus): array
    {
        $identity = self::identity();
        IdentityScenario::registered($identity);

        return [self::build($commandBus, $identity), self::token($identity)];
    }

    /** @param array<string, object> $overrides */
    public static function identity(array $overrides = []): IdentityFixture
    {
        return IdentityFixture::create([
            Settings::class => new SettingsValue([
                'env' => 'test',
                'example-api' => ['api-key' => 'example-api-key'],
                'cdnBaseUrl' => '',
            ]),
            ...$overrides,
        ]);
    }

    public static function token(IdentityFixture $identity, Realm $realm = Realm::USER, string $identifier = 'ada@example.com'): string
    {
        $container = $identity->container();
        $issuer    = $container->get(SessionIssuance::class);
        $clock     = $container->get(AuthenticationClock::class);
        $principal = IdentityScenario::principal($identity, $realm, $identifier);

        return $issuer->issue($principal, $clock->sessionExpiresAt());
    }
}
